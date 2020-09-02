<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Summary Sheet Controller
* @author dev@maarch.org
*/

namespace Resource\controllers;

use Basket\models\BasketModel;
use Contact\controllers\ContactController;
use CustomField\models\CustomFieldModel;
use Endroid\QrCode\QrCode;
use Entity\models\EntityModel;
use Entity\models\ListInstanceModel;
use IndexingModel\models\IndexingModelFieldModel;
use Note\models\NoteEntityModel;
use Note\models\NoteModel;
use Parameter\models\ParameterModel;
use Priority\models\PriorityModel;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use setasign\Fpdi\Tcpdf\Fpdi;
use Slim\Http\Request;
use Slim\Http\Response;
use SrcCore\controllers\PreparedClauseController;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\TextFormatModel;
use SrcCore\models\ValidatorModel;
use Status\models\StatusModel;
use User\models\UserModel;

class SummarySheetController
{
    public function createList(Request $request, Response $response, array $aArgs)
    {
        set_time_limit(240);

        $errors = ResourceListController::listControl(['groupId' => $aArgs['groupId'], 'userId' => $aArgs['userId'], 'basketId' => $aArgs['basketId'], 'currentUserId' => $GLOBALS['id']]);
        if (!empty($errors['errors'])) {
            return $response->withStatus($errors['code'])->withJson(['errors' => $errors['errors']]);
        }

        $bodyData = $request->getParsedBody();
        $units    = empty($bodyData['units']) ? [] : $bodyData['units'];

        if (!Validator::arrayType()->notEmpty()->validate($bodyData['resources'])) {
            return $response->withStatus(403)->withJson(['errors' => 'Resources is not set or empty']);
        }

        $bodyData['resources'] = array_slice($bodyData['resources'], 0, 500);
        $basket = BasketModel::getById(['id' => $aArgs['basketId'], 'select' => ['basket_clause', 'basket_res_order', 'basket_name']]);
        $user   = UserModel::getById(['id' => $aArgs['userId'], 'select' => ['user_id']]);

        $whereClause = PreparedClauseController::getPreparedClause(['clause' => $basket['basket_clause'], 'login' => $user['user_id']]);
        $rawResourcesInBasket = ResModel::getOnView([
            'select'    => ['res_id'],
            'where'     => [$whereClause, 'res_view_letterbox.res_id in (?)'],
            'data'      => [$bodyData['resources']]
        ]);
        $allResourcesInBasket = array_column($rawResourcesInBasket, 'res_id');

//        $order = 'CASE res_view_letterbox.res_id ';
        $order = '';
        foreach ($bodyData['resources'] as $key => $resId) {
            if (!in_array($resId, $allResourcesInBasket)) {
                return $response->withStatus(403)->withJson(['errors' => 'Resources out of perimeter']);
            }
            $order .= "WHEN {$resId} THEN {$key} ";
        }
        $order .= 'END';

        $orderTable = 'CASE res_id ' . $order;
        $resourcesByModelIds = ResModel::get([
            'select'  => ["string_agg(cast(res_id as text), ',' order by {$orderTable}) as res_ids", 'model_id'],
            'where'   => ['res_id in (?)'],
            'data'    => [$bodyData['resources']],
            'groupBy' => ['model_id']
        ]);

        $pdf = new Fpdi('P', 'pt');
        $pdf->setPrintHeader(false);

        $order = 'CASE res_view_letterbox.res_id ' . $order;

        foreach ($resourcesByModelIds as $resourcesByModelId) {
            $resourcesIdsByModel = $resourcesByModelId['res_ids'];
            $resourcesIdsByModel = explode(',', $resourcesIdsByModel);

            $indexingFields   = IndexingModelFieldModel::get([
                'select' => ['identifier', 'unit'],
                'where'  => ['model_id = ?'],
                'data'   => [$resourcesByModelId['model_id']]
            ]);
            $fieldsIdentifier = array_column($indexingFields, 'identifier');

            $select = ['res_id', 'subject', 'alt_identifier'];
            foreach ($units as $unit) {
                $unit = (array)$unit;
                if ($unit['unit'] == 'primaryInformations') {
                    $information = [
                        'documentDate' => 'doc_date',
                        'arrivalDate'  => 'admission_date',
                        'initiator'    => 'initiator'
                    ];
                    $select[]    = 'type_label';
                    $select[]    = 'creation_date';
                    $select[]    = 'typist';

                    foreach ($information as $key => $item) {
                        if (in_array($key, $fieldsIdentifier)) {
                            $select[] = $item;
                        }
                    }
                } elseif ($unit['unit'] == 'secondaryInformations') {
                    $information = [
                        'priority'         => 'priority',
                        'processLimitDate' => 'process_limit_date',
                    ];
                    $select[] = 'category_id';
                    $select[] = 'status';

                    foreach ($information as $key => $item) {
                        if (in_array($key, $fieldsIdentifier)) {
                            $select[] = $item;
                        }
                    }
                } elseif ($unit['unit'] == 'diffusionList') {
                    if (in_array('destination', $fieldsIdentifier)) {
                        $select[] = 'destination';
                    }
                }
            }

            $resources = ResModel::getOnView([
                'select'  => $select,
                'where'   => ['res_view_letterbox.res_id in (?)'],
                'data'    => [$resourcesIdsByModel],
                'orderBy' => [$order]
            ]);

            $resourcesIds = array_column($resources, 'res_id');

            // Data for resources
            $data = SummarySheetController::prepareData(['units' => $units, 'resourcesIds' => $resourcesIds]);

            foreach ($resources as $resource) {
                SummarySheetController::createSummarySheet($pdf, [
                    'resource'         => $resource, 'units' => $units,
                    'login'            => $GLOBALS['login'],
                    'data'             => $data,
                    'fieldsIdentifier' => $fieldsIdentifier
                ]);
            }
        }

        $fileContent = $pdf->Output('', 'S');
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($fileContent);

        $response->write($fileContent);
        $response = $response->withAddedHeader('Content-Disposition', "inline; filename=maarch.pdf");

        return $response->withHeader('Content-Type', $mimeType);
    }

    public static function createSummarySheet(Fpdi $pdf, array $args)
    {
        ValidatorModel::notEmpty($args, ['resource', 'login']);
        ValidatorModel::arrayType($args, ['resource', 'units', 'data', 'fieldsIdentifier']);
        ValidatorModel::stringType($args, ['login']);

        $resource         = $args['resource'];
        $units            = $args['units'];
        $fieldsIdentifier = $args['fieldsIdentifier'];


        $pdf->AddPage();
        $dimensions     = $pdf->getPageDimensions();
        $widthNoMargins = $dimensions['w'] - $dimensions['rm'] - $dimensions['lm'];
        $bottomHeight   = $dimensions['h'] - $dimensions['bm'];

        $widthMultiCell = $widthNoMargins / 10 * 4.5;
        $widthCell      = $widthNoMargins / 10;
        $widthNotes     = $widthNoMargins / 2;
        $specialWidth   = $widthNoMargins / 4;
        $widthAssignee  = $widthNoMargins / 6;

        $appName = CoreConfigModel::getApplicationName();
        $pdf->SetFont('', '', 8);
        $pdf->Cell(0, 20, "$appName / " . date('d-m-Y'), 0, 2, 'L', false);
        $pdf->SetY($pdf->GetY() - 20);

        $pdf->SetFont('', 'B', 12);
        $pdf->Cell(0, 20, _SUMMARY_SHEET, 0, 2, 'C', false);

        $pdf->SetFont('', '', 8);
        $pdf->Cell(0, 1, $resource['alt_identifier'], 0, 2, 'C', false);

        $subject = str_replace("\n", ' ', $resource['subject']);

        $pdf->SetY($pdf->GetY() + 15);
        $pdf->SetFont('', 'B', 16);
        $pdf->MultiCell(0, 1, $subject, 1, 'C', false);

        foreach ($units as $key => $unit) {
            $units[$key] = (array)$unit;
            $unit        = (array)$unit;
            if ($unit['unit'] == 'qrcode') {
                $parameter = ParameterModel::getById(['select' => ['param_value_int'], 'id' => 'QrCodePrefix']);
                $prefix = '';
                if ($parameter['param_value_int'] == 1) {
                    $prefix = 'MAARCH_';
                }
                $qrCode = new QrCode($prefix . $resource['res_id']);
                $qrCode->setSize(400);
                $qrCode->setMargin(25);
                $pdf->Image('@'.$qrCode->writeString(), 485, 10, 90, 90);
            }
        }
        foreach ($units as $key => $unit) {
            if ($unit['unit'] == 'primaryInformations') {
                $admissionDate = null;
                if (in_array('arrivalDate', $fieldsIdentifier)) {
                    $admissionDate = TextFormatModel::formatDate($resource['admission_date'], 'd-m-Y');
                    $admissionDate = empty($admissionDate) ? '<i>' . _UNDEFINED . '</i>' : "<b>{$admissionDate}</b>";
                }

                $creationdate  = TextFormatModel::formatDate($resource['creation_date'], 'd-m-Y');
                $creationdate  = empty($creationdate) ? '<i>'._UNDEFINED.'</i>' : "<b>{$creationdate}</b>";

                $docDate = null;
                if (in_array('documentDate', $fieldsIdentifier)) {
                    $docDate = TextFormatModel::formatDate($resource['doc_date'], 'd-m-Y');
                    $docDate = empty($docDate) ? '<i>' . _UNDEFINED . '</i>' : "<b>{$docDate}</b>";
                }

                if (!empty($resource['initiator'])) {
                    $initiator = EntityModel::getByEntityId(['entityId' => $resource['initiator'], 'select' => ['short_label']]);
                }
                $initiatorEntity = empty($initiator) ? '' : "({$initiator['short_label']})";

                $typist          = UserModel::getLabelledUserById(['id' => $resource['typist']]);
                $doctype         = empty($resource['type_label']) ? '<i>'._UNDEFINED.'</i>' : "<b>{$resource['type_label']}</b>";

                $pdf->SetY($pdf->GetY() + 40);
                if (($pdf->GetY() + 77) > $bottomHeight) {
                    $pdf->AddPage();
                }
                if (!($key == 0 || ($key == 1 && $units[0]['unit'] == 'qrcode'))) {
                    $pdf->SetFont('', 'B', 11);
                    $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);
                    $pdf->SetY($pdf->GetY() + 2);
                }

                $pdf->SetFont('', '', 10);

                $pdf->MultiCell($widthMultiCell, 15, _CREATED . " : {$creationdate}", 0, 'L', false, 0, '', '', true, 0, true);

                if (isset($docDate)) {
                    $pdf->Cell($widthCell, 15, '', 0, 0, 'L', false);
                    $pdf->MultiCell($widthMultiCell, 15, _DOC_DATE . " : {$docDate}", 0, 'L', false, 1, '', '', true, 0, true);
                } else {
                    $pdf->Cell($widthCell, 15, '', 0, 1, 'L', false);
                }

                if (isset($admissionDate)) {
                    $pdf->MultiCell($widthMultiCell, 15, _ADMISSION_DATE . " : {$admissionDate}", 0, 'L', false, 1, '', '', true, 0, true);
                }

                $pdf->MultiCell($widthMultiCell * 2, 15, _TYPIST . " : <b>{$typist} {$initiatorEntity}</b>", 0, 'L', false, 1, '', '', true, 0, true);

                $pdf->MultiCell($widthMultiCell * 2, 15, _DOCTYPE . " : {$doctype}", 0, 'L', false, 0, '', '', true, 0, true);
                $pdf->Cell($widthCell, 15, '', 0, 0, 'L', false);
            } elseif ($unit['unit'] == 'secondaryInformations') {
                $category = ResModel::getCategoryLabel(['categoryId' => $resource['category_id']]);
                $category = empty($category) ? '<i>'._UNDEFINED.'</i>' : "<b>{$category}</b>";

                $status = StatusModel::getById(['id' => $resource['status'], 'select' => ['label_status']]);
                $status = empty($status['label_status']) ? '<i>' . _UNDEFINED . '</i>' : "<b>{$status['label_status']}</b>";

                $priority = null;
                if (in_array('priority', $fieldsIdentifier)) {
                    $priority = '';
                    if (!empty($resource['priority'])) {
                        $priority = PriorityModel::getById(['id' => $resource['priority'], 'select' => ['label']]);
                    }
                    $priority = empty($priority['label']) ? '<i>' . _UNDEFINED . '</i>' : "<b>{$priority['label']}</b>";
                }

                $processLimitDate = null;
                if (in_array('processLimitDate', $fieldsIdentifier)) {
                    $processLimitDate = TextFormatModel::formatDate($resource['process_limit_date'], 'd-m-Y');
                    $processLimitDate = empty($processLimitDate) ? '<i>' . _UNDEFINED . '</i>' : "<b>{$processLimitDate}</b>";
                }

                // Custom fields
                $customFieldsValues = ResModel::get([
                    'select' => ['custom_fields'],
                    'where' => ['res_id = ?'],
                    'data' => [$resource['res_id']]
                ]);
                // Get all the ids of the custom fields in the model
                $customFieldsIds = [];
                foreach ($fieldsIdentifier as $item) {
                    if (strpos($item, 'indexingCustomField_') !== false) {
                        $customFieldsIds[] = explode('_', $item)[1];
                    }
                }

                if (!empty($customFieldsIds)) {
                    // get the label of the custom fields
                    $customFields = CustomFieldModel::get([
                        'select' => ['id', 'label', 'values', 'type'],
                        'where'  => ['id in (?)'],
                        'data'   => [$customFieldsIds]
                    ]);

                    $customFieldsRawValues = array_column($customFields, 'values', 'id');
                    $customFieldsRawTypes = array_column($customFields, 'type', 'id');
                    $customFields = array_column($customFields, 'label', 'id');

                    $customFieldsValues = $customFieldsValues[0]['custom_fields'] ?? null;
                    $customFieldsValues = json_decode($customFieldsValues, true);
                }

                $pdf->SetY($pdf->GetY() + 40);
                if (($pdf->GetY() + 57) > $bottomHeight) {
                    $pdf->AddPage();
                }
                $pdf->SetFont('', 'B', 11);
                $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);
                $pdf->SetY($pdf->GetY() + 2);

                $pdf->SetFont('', '', 10);
                $pdf->MultiCell($widthNotes, 30, _CATEGORY . " : {$category}", 1, 'L', false, 0, '', '', true, 0, true);

                $pdf->MultiCell($widthNotes, 30, _STATUS . " : {$status}", 1, 'L', false, 1, '', '', true, 0, true);

                $nextLine = 1;
                if (isset($priority)) {
                    $nextLine = isset($processLimitDate) || !empty($customFieldsIds) ? 0 : 1;
                    $pdf->MultiCell($widthNotes, 30, _PRIORITY . " : {$priority}", 1, 'L', false, $nextLine, '', '', true, 0, true);
                }
                if (isset($processLimitDate)) {
                    $nextLine = !empty($customFieldsIds) && $nextLine == 0 ? 1 : 0;
                    $pdf->MultiCell($widthNotes, 30, _PROCESS_LIMIT_DATE . " : {$processLimitDate}", 1, 'L', false, $nextLine, '', '', true, 0, true);
                }

                if (!empty($customFieldsIds)) {
                    foreach ($customFieldsIds as $customFieldsId) {
                        $label = $customFields[$customFieldsId];
                        $rawValues = json_decode($customFieldsRawValues[$customFieldsId], true);
                        if (!empty($rawValues['table']) && in_array($customFieldsRawTypes[$customFieldsId], ['radio', 'select', 'checkbox'])) {
                            $rawValues = CustomFieldModel::getValuesSQL($rawValues);

                            $rawValues = array_column($rawValues, 'label', 'key');
                            if (is_array($customFieldsValues[$customFieldsId])) {
                                foreach ($customFieldsValues[$customFieldsId] as $key => $value) {
                                    $customFieldsValues[$customFieldsId][$key] = $rawValues[$value];
                                }
                            } else {
                                $customFieldsValues[$customFieldsId] = $rawValues[$customFieldsValues[$customFieldsId]];
                            }
                        }
                        if (is_array($customFieldsValues[$customFieldsId])) {
                            if (!empty($customFieldsValues[$customFieldsId])) {
                                if (is_array($customFieldsValues[$customFieldsId][0])) { //Custom BAN
                                    $customValue = "{$customFieldsValues[$customFieldsId][0]['addressNumber']} {$customFieldsValues[$customFieldsId][0]['addressStreet']} {$customFieldsValues[$customFieldsId][0]['addressTown']} ({$customFieldsValues[$customFieldsId][0]['addressPostcode']})";
                                } else {
                                    $customValue = implode(',', $customFieldsValues[$customFieldsId]);
                                }
                            }
                            $value = !empty($customValue) ? '<b>' . $customValue . '</b>' : '<i>' . _UNDEFINED . '</i>';
                        } else {
                            $value = $customFieldsValues[$customFieldsId] ? '<b>' . $customFieldsValues[$customFieldsId] . '</b>' : '<i>' . _UNDEFINED . '</i>';
                        }

                        $nextLine = ($nextLine + 1) % 2;
                        $pdf->MultiCell($widthNotes, 30, $label . " : {$value}", 1, 'L', false, $nextLine, '', '', true, 0, true);
                    }
                }
            } elseif ($unit['unit'] == 'senderRecipientInformations') {
                $senders = null;
                if (in_array('senders', $fieldsIdentifier)) {
                    $senders = ContactController::getFormattedContacts([
                        'resId' => $resource['res_id'],
                        'mode'  => 'sender'
                    ]);
                    if (!empty($senders) && count($senders) > 2) {
                        $nbSenders = count($senders);
                        $senders = [];
                        $senders[0] = $nbSenders . ' ' . _CONTACTS;
                    } elseif (empty($senders)) {
                        $senders = [''];
                    }
                }

                $recipients = null;
                if (in_array('recipients', $fieldsIdentifier)) {
                    $recipients = ContactController::getFormattedContacts([
                        'resId' => $resource['res_id'],
                        'mode'  => 'recipient'
                    ]);
                    if (!empty($recipients) && count($recipients) > 2) {
                        $nbRecipients = count($recipients);
                        $recipients = [];
                        $recipients[0] = $nbRecipients . ' ' . _CONTACTS;
                    } elseif (empty($recipients)) {
                        $recipients = [''];
                    }
                }

                // If senders and recipients are both null, they are not part of the model so we continue to the next unit
                if ($senders === null && $recipients === null) {
                    continue;
                }

                $pdf->SetY($pdf->GetY() + 40);
                if (($pdf->GetY() + 57) > $bottomHeight) {
                    $pdf->AddPage();
                }
                $pdf->SetFont('', 'B', 11);
                $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);
                $pdf->SetY($pdf->GetY() + 2);

                $pdf->SetFont('', '', 10);

                $correspondents = [];
                if ($senders !== null && $recipients !== null) {
                    if (empty($senders[0]) && empty($recipients[0])) {
                        $correspondents = [null, null];
                    } else {
                        for ($i = 0; !empty($senders[$i]) || !empty($recipients[$i]); $i++) {
                            $correspondents[] = $senders[$i] ?? null;
                            $correspondents[] = $recipients[$i] ?? null;
                        }
                    }

                    $pdf->Cell($widthMultiCell, 15, _SENDERS, 1, 0, 'C', false);
                    $pdf->Cell($widthCell, 15, '', 0, 0, 'C', false);
                    $pdf->Cell($widthMultiCell, 15, _RECIPIENTS, 1, 1, 'C', false);
                } elseif ($senders !== null && $recipients === null) {
                    $correspondents = $senders;

                    $pdf->Cell($widthMultiCell, 15, _SENDERS, 1, 1, 'C', false);
                } elseif ($senders === null && $recipients !== null) {
                    $correspondents = $recipients;

                    $pdf->Cell($widthMultiCell, 15, _RECIPIENTS, 1, 1, 'C', false);
                }

                // allow to skip an element in the senders or recipients column if we already printed UNDEFINED once
                $columnUndefined = [false, false];
                $nextLine = 1;
                foreach ($correspondents as $correspondent) {
                    // if senders and recipients are not null, nextLine alternate between 0 and 1, otherwise its always 1
                    if ($senders !== null && $recipients !== null) {
                        $nextLine = ($nextLine + 1) % 2;

                        if ($columnUndefined[$nextLine]) {
                            $pdf->MultiCell($widthMultiCell, 40, '', 0, 'L', false, 0, '', '', true, 0, true);
                            $pdf->MultiCell($widthCell, 40, '', 0, 'L', false, $nextLine, '', '', true, 0, true);
                            continue;
                        }
                    } else {
                        $nextLine = 1;
                    }

                    if (empty($correspondent)) {
                        $columnUndefined[$nextLine] = true;
                        $pdf->MultiCell($widthMultiCell, 40, _UNDEFINED, 1, 'L', false, $nextLine, '', '', true, 0, true);
                    } else {
                        $pdf->MultiCell($widthMultiCell, 40, empty($correspondent) ? '' : $correspondent, empty($correspondent) ? 0 : 1, 'L', false, $nextLine, '', '', true, 0, true);
                    }

                    if ($nextLine == 0) {
                        $pdf->MultiCell($widthCell, 40, '', 0, 'L', false, 0, '', '', true, 0, true);
                    }
                }
            } elseif ($unit['unit'] == 'diffusionList') {
                $assignee    = '';
                $destination = '';
                $found       = false;
                $roles = EntityModel::getRoles();
                $rolesItems = [];
                $nbItems = 0;
                foreach ($args['data']['listInstances'] as $listKey => $listInstance) {
                    if ($found && $listInstance['res_id'] != $resource['res_id']) {
                        break;
                    } elseif ($listInstance['res_id'] == $resource['res_id']) {
                        $item = '';
                        if ($listInstance['item_type'] == 'user_id') {
                            $user = UserModel::getById(['id' => $listInstance['item_id'], 'select' => ['id', 'firstname', 'lastname']]);
                            $entity = UserModel::getPrimaryEntityById(['id' => $user['id'], 'select' => ['entities.entity_label']]);

                            if ($listInstance['item_mode'] == 'dest') {
                                $item = $user['firstname'] . ' ' . $user['lastname'];
                            } else {
                                $item = "{$user['firstname']} {$user['lastname']} ({$entity['entity_label']})";
                            }
                        } elseif ($listInstance['item_type'] == 'entity_id') {
                            $item = $listInstance['item_id'];
                            $entity = EntityModel::getById(['id' => $listInstance['item_id'], 'select' => ['short_label']]);
                            if (!empty($entity)) {
                                $item = "{$entity['short_label']} ({$item})";
                            }
                        }
                        if ($listInstance['item_mode'] == 'dest') {
                            $assignee = $item;
                        } else {
                            foreach ($roles as $role) {
                                if ($listInstance['item_mode'] == $role['id'] || ($listInstance['item_mode'] == 'cc' && $role['id'] == 'copy')) {
                                    $rolesItems[$role['id']]['item'][] = $item;
                                    $rolesItems[$role['id']]['label'] = $role['label'];
                                    $nbItems++;
                                    continue;
                                }
                            }
                        }
                        unset($args['data']['listInstances'][$listKey]);
                        $found = true;
                    }
                }

                // Sort keys to be in the same order defined in the roles.xml file
                $rolesIDs = array_column($roles, 'id');
                $tmp = [];
                foreach ($rolesIDs as $key) {
                    if (!empty($rolesItems[$key])) {
                        $tmp[$key] = $rolesItems[$key];
                    }
                }
                $rolesItems = $tmp;

                if (!empty($resource['destination'])) {
                    $destination = EntityModel::getByEntityId(['entityId' => $resource['destination'], 'select' => ['short_label']]);
                }
                $destinationEntity = empty($destination) ? '' : "({$destination['short_label']})";

                if (empty($assignee)) {
                    $assignee = _UNDEFINED;
                }
                $pdf->SetY($pdf->GetY() + 40);
                if (($pdf->GetY() + 37 + $nbItems * 20) > $bottomHeight) {
                    $pdf->AddPage();
                }
                $pdf->SetFont('', 'B', 11);
                $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);
                $pdf->SetY($pdf->GetY() + 2);

                $pdf->SetFont('', '', 10);
                $pdf->MultiCell($widthAssignee, 20, _ASSIGNEE, 1, 'C', false, 0, '', '', true, 0, false, true, 20, 'M');
                $pdf->SetFont('', 'B', 10);
                $pdf->Cell($widthAssignee * 5, 20, "- {$assignee} {$destinationEntity}", 1, 1, 'L', false);

                foreach ($rolesItems as $rolesItem) {
                    $pdf->SetFont('', '', 10);
                    $pdf->MultiCell($widthAssignee, count($rolesItem['item']) * 20, $rolesItem['label'], 1, 'C', false, 0, '', '', true, 0, false, true, count($rolesItem['item']) * 20, 'M');

                    $nbItems = count($rolesItem['item']);
                    $i = 0;
                    foreach ($rolesItem['item'] as $item) {
                        $nextLine = $i == ($nbItems - 1) ? 1 : 2;
                        $pdf->Cell($widthAssignee * 5, 20, "- {$item}", 1, $nextLine, 'L', false);
                        $i++;
                    }
                }
            } elseif ($unit['unit'] == 'visaWorkflow') {
                $users = [];
                $found = false;
                foreach ($args['data']['listInstancesVisa'] as $listKey => $listInstance) {
                    if ($found && $listInstance['res_id'] != $resource['res_id']) {
                        break;
                    } elseif ($listInstance['res_id'] == $resource['res_id']) {
                        $users[] = [
                            'user'  => UserModel::getLabelledUserById(['id' => $listInstance['item_id']]),
                            'mode'  => $listInstance['requested_signature'] ? 'Signataire' : 'Viseur',
                            'date'  => TextFormatModel::formatDate($listInstance['process_date']),
                        ];
                        unset($args['data']['listInstancesVisa'][$listKey]);
                        $found = true;
                    }
                }

                if (!empty($users)) {
                    $pdf->SetY($pdf->GetY() + 40);
                    if (($pdf->GetY() + 37 + count($users) * 20) > $bottomHeight) {
                        $pdf->AddPage();
                    }
                    $pdf->SetFont('', 'B', 11);
                    $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);
                    $pdf->SetY($pdf->GetY() + 2);

                    $pdf->SetFont('', '', 10);
                    $pdf->Cell($specialWidth * 3, 20, _USERS, 1, 0, 'L', false);
                    $pdf->Cell($specialWidth, 20, _ACTION_DATE, 1, 1, 'L', false);
                    foreach ($users as $keyUser => $user) {
                        $pdf->Cell($specialWidth * 3, 20, $keyUser + 1 . ". {$user['user']} ({$user['mode']})", 1, 0, 'L', false);
                        $pdf->Cell($specialWidth, 20, $user['date'], 1, 1, 'L', false);
                    }
                }
            } elseif ($unit['unit'] == 'opinionWorkflow') {
                $users = [];
                $found = false;
                foreach ($args['data']['listInstancesOpinion'] as $listKey => $listInstance) {
                    if ($found && $listInstance['res_id'] != $resource['res_id']) {
                        break;
                    } elseif ($listInstance['res_id'] == $resource['res_id']) {
                        $user = UserModel::getById(['id' => $listInstance['item_id'], 'select' => ['id', 'firstname', 'lastname']]);
                        $entity = UserModel::getPrimaryEntityById(['id' => $user['id'], 'select' => ['entities.entity_label']]);

                        $userLabel = $user['firstname'] . ' ' .$user['lastname'] . " (" . $entity['entity_label'] . ")";
                        $users[] = [
                            'user'  => $userLabel,
                            'date'  => TextFormatModel::formatDate($listInstance['process_date']),
                        ];
                        unset($args['data']['listInstancesOpinion'][$listKey]);
                        $found = true;
                    }
                }

                if (!empty($users)) {
                    $pdf->SetY($pdf->GetY() + 40);
                    if (($pdf->GetY() + 37 + count($users) * 20) > $bottomHeight) {
                        $pdf->AddPage();
                    }
                    $pdf->SetFont('', 'B', 11);
                    $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);
                    $pdf->SetY($pdf->GetY() + 2);

                    $pdf->SetFont('', '', 10);
                    $pdf->Cell($specialWidth * 3, 20, _USERS, 1, 0, 'L', false);
                    $pdf->Cell($specialWidth, 20, _ACTION_DATE, 1, 1, 'L', false);
                    foreach ($users as $keyUser => $user) {
                        $pdf->Cell($specialWidth * 3, 20, $keyUser + 1 . ". {$user['user']}", 1, 0, 'L', false);
                        $pdf->Cell($specialWidth, 20, $user['date'], 1, 1, 'L', false);
                    }
                }
            } elseif ($unit['unit'] == 'notes') {
                $notes = [];
                $found = false;
                $user = UserModel::getByLogin(['select' => ['id'], 'login' => $args['login']]);
                foreach ($args['data']['notes'] as $noteKey => $rawNote) {
                    if ($found && $rawNote['identifier'] != $resource['res_id']) {
                        break;
                    } elseif ($rawNote['identifier'] == $resource['res_id']) {
                        $allowed = false;
                        if ($rawNote['user_id'] == $user['id']) {
                            $allowed = true;
                        } else {
                            $noteEntities = NoteEntityModel::get(['select' => ['item_id'], 'where' => ['note_id = ?'], 'data' => [$rawNote['id']]]);
                            if (!empty($noteEntities)) {
                                foreach ($noteEntities as $noteEntity) {
                                    if (in_array($noteEntity['item_id'], $args['data']['userEntities'])) {
                                        $allowed = true;
                                        break;
                                    }
                                }
                            } else {
                                $allowed = true;
                            }
                        }
                        if ($allowed) {
                            $notes[] = [
                                'user'  => UserModel::getLabelledUserById(['id' => $rawNote['user_id']]),
                                'date'  => TextFormatModel::formatDate($rawNote['creation_date']),
                                'note'  => $noteText = str_replace('←', '<=', $rawNote['note_text'])
                            ];
                        }
                        unset($args['data']['notes'][$noteKey]);
                        $found = true;
                    }
                }

                if (!empty($notes)) {
                    $pdf->SetY($pdf->GetY() + 40);
                    if (($pdf->GetY() + 80) > $bottomHeight) {
                        $pdf->AddPage();
                    }

                    $pdf->SetFont('', 'B', 11);
                    $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);

                    $pdf->SetY($pdf->GetY() + 2);
                    $pdf->SetFont('', '', 10);

                    foreach ($notes as $note) {
                        if (($pdf->GetY() + 65) > $bottomHeight) {
                            $pdf->AddPage();
                        }
                        $pdf->SetFont('', 'B', 10);
                        $pdf->Cell($widthNotes, 20, $note['user'], 1, 0, 'L', false);
                        $pdf->SetFont('', '', 10);
                        $pdf->Cell($widthNotes, 20, $note['date'], 1, 1, 'L', false);
                        $pdf->MultiCell(0, 40, $note['note'], 1, 'L', false);
                        $pdf->SetY($pdf->GetY() + 5);
                    }
                }
            } elseif ($unit['unit'] == 'freeField') {
                $pdf->SetY($pdf->GetY() + 40);
                if (($pdf->GetY() + 77) > $bottomHeight) {
                    $pdf->AddPage();
                }
                $pdf->SetFont('', 'B', 11);
                $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);

                $pdf->SetY($pdf->GetY() + 2);
                $pdf->Cell(0, 60, '', 1, 2, 'L', false);
            } elseif ($unit['unit'] == 'trafficRecords') {
                $pdf->SetY($pdf->GetY() + 40);
                if (($pdf->GetY() + 77) > $bottomHeight) {
                    $pdf->AddPage();
                }
                $pdf->SetFont('', 'B', 11);
                $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);
                $pdf->SetFont('', '', 9);

                $parameter = ParameterModel::getById(['select' => ['param_value_string'], 'id' => 'traffic_record_summary_sheet']);
                $pdf->writeHTMLCell(640, 0, 25, $pdf->GetY(), $parameter['param_value_string']);
                // $pdf->writeHTML($parameter['param_value_string']);
            }
        }
    }

    public static function prepareData(array $args)
    {
        $units = $args['units'];
        $tmpIds = $args['resourcesIds'];

        $data = [];
        foreach ($units as $unit) {
            if ($unit['unit'] == 'notes') {
                $data['notes'] = NoteModel::get([
                    'select'   => ['id', 'note_text', 'user_id', 'creation_date', 'identifier'],
                    'where'    => ['identifier in (?)'],
                    'data'     => [$tmpIds],
                    'order_by' => ['identifier']]);

                $userEntities = EntityModel::getByUserId(['userId' => $GLOBALS['id'], 'select' => ['entity_id']]);
                $data['userEntities'] = [];
                foreach ($userEntities as $userEntity) {
                    $data['userEntities'][] = $userEntity['entity_id'];
                }
            } elseif ($unit['unit'] == 'opinionWorkflow') {
                $data['listInstancesOpinion'] = ListInstanceModel::get([
                    'select'    => ['item_id', 'process_date', 'res_id'],
                    'where'     => ['difflist_type = ?', 'res_id in (?)'],
                    'data'      => ['AVIS_CIRCUIT', $tmpIds],
                    'orderBy'   => ['listinstance_id']
                ]);
            } elseif ($unit['unit'] == 'visaWorkflow') {
                $data['listInstancesVisa'] = ListInstanceModel::get([
                    'select'    => ['item_id', 'requested_signature', 'process_date', 'res_id'],
                    'where'     => ['difflist_type = ?', 'res_id in (?)'],
                    'data'      => ['VISA_CIRCUIT', $tmpIds],
                    'orderBy'   => ['listinstance_id']
                ]);
            } elseif ($unit['unit'] == 'diffusionList') {
                $data['listInstances'] = ListInstanceModel::get([
                    'select' => ['item_id', 'item_type', 'item_mode', 'res_id'],
                    'where'  => ['difflist_type = ?', 'res_id in (?)'],
                    'data'   => ['entity_id', $tmpIds],
                    'orderBy' => ['listinstance_id']
                ]);
            }
        }

        return $data;
    }
}

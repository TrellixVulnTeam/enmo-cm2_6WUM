<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Email Controller
 * @author dev@maarch.org
 */

namespace Email\controllers;

use Attachment\controllers\AttachmentController;
use Attachment\models\AttachmentModel;
use Configuration\models\ConfigurationModel;
use Convert\models\AdrModel;
use Docserver\models\DocserverModel;
use Docserver\models\DocserverTypeModel;
use Email\models\EmailModel;
use Entity\models\EntityModel;
use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use History\models\HistoryModel;
use MessageExchange\models\MessageExchangeModel;
use Note\controllers\NoteController;
use Note\models\NoteEntityModel;
use Note\models\NoteModel;
use PHPMailer\PHPMailer\PHPMailer;
use Resource\controllers\ResController;
use Resource\controllers\StoreController;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use Slim\Http\Request;
use Slim\Http\Response;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\PasswordModel;
use SrcCore\models\TextFormatModel;
use SrcCore\models\ValidatorModel;
use User\models\UserModel;

class EmailController
{
    public function send(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'sendmail', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        $isSent = EmailController::createEmail(['userId' => $GLOBALS['id'], 'data' => $body]);

        if (!empty($isSent['errors'])) {
            $httpCode = empty($isSent['code']) ? 400 : $isSent['code'];
            return $response->withStatus($httpCode)->withJson(['errors' => $isSent['errors']]);
        }

        return $response->withJson(['id' => $isSent]);
    }

    public static function createEmail(array $args)
    {
        ValidatorModel::notEmpty($args, ['userId', 'data']);
        ValidatorModel::intVal($args, ['userId']);
        ValidatorModel::arrayType($args, ['data', 'options']);

        $check = EmailController::controlCreateEmail(['userId' => $args['userId'], 'data' => $args['data']]);
        if (!empty($check['errors'])) {
            return ['errors' => $check['errors'], 'code' => $check['code']];
        }

        $id = EmailModel::create([
            'userId'                => $args['userId'],
            'sender'                => empty($args['data']['sender']) ? '{}' : json_encode($args['data']['sender']),
            'recipients'            => empty($args['data']['recipients']) ? '[]' : json_encode($args['data']['recipients']),
            'cc'                    => empty($args['data']['cc']) ? '[]' : json_encode($args['data']['cc']),
            'cci'                   => empty($args['data']['cci']) ? '[]' : json_encode($args['data']['cci']),
            'object'                => empty($args['data']['object']) ? null : $args['data']['object'],
            'body'                  => empty($args['data']['body']) ? null : $args['data']['body'],
            'document'              => empty($args['data']['document']) ? null : json_encode($args['data']['document']),
            'isHtml'                => $args['data']['isHtml'] ? 'true' : 'false',
            'status'                => $args['data']['status'] == 'DRAFT' ? 'DRAFT' : 'WAITING',
            'messageExchangeId'     => empty($args['data']['messageExchangeId']) ? null : $args['data']['messageExchangeId']
        ]);

        $isSent = ['success' => 'success'];
        if ($args['data']['status'] != 'DRAFT') {
            if ($args['data']['status'] == 'EXPRESS') {
                $isSent = EmailController::sendEmail(['emailId' => $id, 'userId' => $args['userId']]);
                if (!empty($isSent['success'])) {
                    EmailModel::update(['set' => ['status' => 'SENT', 'send_date' => 'CURRENT_TIMESTAMP'], 'where' => ['id = ?'], 'data' => [$id]]);
                } else {
                    EmailModel::update(['set' => ['status' => 'ERROR', 'send_date' => 'CURRENT_TIMESTAMP'], 'where' => ['id = ?'], 'data' => [$id]]);
                }
            } else {
                $customId = CoreConfigModel::getCustomId();
                if (empty($customId)) {
                    $customId = 'null';
                }
                $encryptKey = CoreConfigModel::getEncryptKey();
                $options = empty($args['options']) ? '' : serialize($args['options']);
                exec("php src/app/email/scripts/sendEmail.php {$customId} {$id} {$args['userId']} '{$encryptKey}' '{$options}' > /dev/null &");
            }
            if (!empty($isSent)) {
                HistoryController::add([
                    'tableName' => 'emails',
                    'recordId'  => $id,
                    'eventType' => 'ADD',
                    'eventId'   => 'emailCreation',
                    'info'      => _EMAIL_ADDED
                ]);

                if (!empty($args['data']['document']['id'])) {
                    HistoryController::add([
                        'tableName' => 'res_letterbox',
                        'recordId'  => $args['data']['document']['id'],
                        'eventType' => 'ADD',
                        'eventId'   => 'emailCreation',
                        'info'      => _EMAIL_ADDED
                    ]);
                }
            }
        } else {
            HistoryController::add([
                'tableName'    => 'emails',
                'recordId'     => $id,
                'eventType'    => 'ADD',
                'eventId'      => 'emailDraftCreation',
                'info'         => _EMAIL_DRAFT_SAVED
            ]);

            if (!empty($args['data']['document']['id'])) {
                HistoryController::add([
                    'tableName' => 'res_letterbox',
                    'recordId'  => $args['data']['document']['id'],
                    'eventType' => 'ADD',
                    'eventId'   => 'emailDraftCreation',
                    'info'      => _EMAIL_DRAFT_SAVED
                ]);
            }
        }

        if (!empty($isSent['errors'])) {
            return $isSent;
        }

        return $id;
    }

    public function getById(Request $request, Response $response, array $args)
    {
        $rawEmail = EmailModel::getById(['id' => $args['id']]);
        $document = json_decode($rawEmail['document'], true);

        if (!empty($document['id']) && !ResController::hasRightByResId(['resId' => [$document['id']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $email = [
            'id'            => $rawEmail['id'],
            'sender'        => json_decode($rawEmail['sender'], true),
            'to'            => json_decode($rawEmail['recipients'], true),
            'cc'            => json_decode($rawEmail['cc'], true),
            'cci'           => json_decode($rawEmail['cci'], true),
            'userId'        => $rawEmail['user_id'],
            'object'        => $rawEmail['object'],
            'body'          => $rawEmail['body'],
            'isHtml'        => $rawEmail['is_html'],
            'status'        => $rawEmail['status'],
            'creationDate'  => $rawEmail['creation_date'],
            'sendDate'      => $rawEmail['send_date'],
            'document'      => $document
        ];
        
        return $response->withJson($email);
    }

    public function update(Request $request, Response $response, array $args)
    {
        $body = $request->getParsedBody();

        $check = EmailController::controlCreateEmail(['userId' => $GLOBALS['id'], 'data' => $body]);
        if (!empty($check['errors'])) {
            return $response->withStatus($check['code'])->withJson(['errors' => $check['errors']]);
        }

        EmailModel::update([
            'set' => [
                'sender'      => empty($body['sender']) ? '{}' : json_encode($body['sender']),
                'recipients'  => empty($body['recipients']) ? '[]' : json_encode($body['recipients']),
                'cc'          => empty($body['cc']) ? '[]' : json_encode($body['cc']),
                'cci'         => empty($body['cci']) ? '[]' : json_encode($body['cci']),
                'object'      => empty($body['object']) ? null : $body['object'],
                'body'        => empty($body['body']) ? null : $body['body'],
                'document'    => empty($body['document']) ? null : json_encode($body['document']),
                'is_html'     => $body['isHtml'] ? 'true' : 'false',
                'status'      => $body['status'] == 'DRAFT' ? 'DRAFT' : 'WAITING'
            ],
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        if ($body['status'] != 'DRAFT') {
            $customId = CoreConfigModel::getCustomId();
            if (empty($customId)) {
                $customId = 'null';
            }
            $encryptKey = CoreConfigModel::getEncryptKey();
            exec("php src/app/email/scripts/sendEmail.php {$customId} {$args['id']} {$GLOBALS['id']} '{$encryptKey}' > /dev/null &");

            HistoryController::add([
                'tableName' => 'emails',
                'recordId'  => $args['emailId'],
                'eventType' => 'ADD',
                'eventId'   => 'emailCreation',
                'info'      => _EMAIL_ADDED
            ]);

            if (!empty($args['data']['document']['id'])) {
                HistoryController::add([
                    'tableName' => 'res_letterbox',
                    'recordId'  => $args['data']['document']['id'],
                    'eventType' => 'ADD',
                    'eventId'   => 'emailCreation',
                    'info'      => _EMAIL_ADDED
                ]);
            }
        } else {
            HistoryController::add([
                'tableName'    => 'emails',
                'recordId'     => $args['emailId'],
                'eventType'    => 'UP',
                'eventId'      => 'emailModification',
                'info'         => _EMAIL_UPDATED
            ]);

            if (!empty($args['data']['document']['id'])) {
                HistoryController::add([
                    'tableName' => 'res_letterbox',
                    'recordId'  => $args['data']['document']['id'],
                    'eventType' => 'UP',
                    'eventId'   => 'emailModification',
                    'info'      => _EMAIL_UPDATED
                ]);
            }
        }

        return $response->withStatus(204);
    }

    public function delete(Request $request, Response $response, array $args)
    {
        $email = EmailModel::getById(['select' => ['user_id', 'document'], 'id' => $args['id']]);
        if (empty($email)) {
            return $response->withStatus(400)->withJson(['errors' => 'Email does not exist']);
        }
        if ($email['user_id'] != $GLOBALS['id']) {
            return $response->withStatus(403)->withJson(['errors' => 'Email out of perimeter']);
        }

        EmailModel::delete([
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        HistoryController::add([
            'tableName'    => 'emails',
            'recordId'     => $args['id'],
            'eventType'    => 'DEL',
            'eventId'      => 'emailDeletion',
            'info'         => _EMAIL_REMOVED
        ]);

        if (!empty($email['document'])) {
            $document = (array)json_decode($email['document']);

            HistoryController::add([
                'tableName' => 'res_letterbox',
                'recordId'  => $document['id'],
                'eventType' => 'DEL',
                'eventId'   => 'emailDeletion',
                'info'      => _EMAIL_REMOVED
            ]);
        }

        return $response->withStatus(204);
    }

    public function getByResId(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['resId']) || !ResController::hasRightByResId(['resId' => [$args['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $queryParams = $request->getQueryParams();
        if (!empty($queryParams['limit']) && !Validator::intVal()->validate($queryParams['limit'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query limit is not an int value']);
        }

        $where = ['document->>\'id\' = ?'];

        if (!empty($queryParams['type'])) {
            if (!Validator::stringType()->validate($queryParams['type'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Query type is not a string value']);
            }

            if ($queryParams['type'] == 'ar') {
                $where[] = "object LIKE '[AR]%'";
            } elseif ($queryParams['type'] == 'm2m') {
                $where[] = 'message_exchange_id is not null';
            } elseif ($queryParams['type'] == 'email') {
                $where[] = "object NOT LIKE '[AR]%'";
                $where[] = 'message_exchange_id is null';
            }
        }

        $emails = EmailModel::get([
            'select' => ['*'],
            'where'  => $where,
            'data'   => [$args['resId']],
            'limit'  => (int)$queryParams['limit']
        ]);

        foreach ($emails as $key => $email) {
            $emails[$key]['sender'] = json_decode($emails[$key]['sender']);
            $emails[$key]['recipients'] = json_decode($emails[$key]['recipients']);
            $emails[$key]['cc'] = json_decode($emails[$key]['cc']);
            $emails[$key]['cci'] = json_decode($emails[$key]['cci']);
            $emails[$key]['document'] = json_decode($emails[$key]['document']);
        }

        return $response->withJson(['emails' => $emails]);
    }

    public static function getAvailableEmails(Request $request, Response $response)
    {
        $emails = [];

        // User's email
        $emailCurrentUser = UserModel::getById(['select' => ['firstname', 'lastname', 'mail'], 'id' => $GLOBALS['id']]);

        $emails[] = [
            'label' => $emailCurrentUser['firstname'] . ' ' . $emailCurrentUser['lastname'],
            'email' => $emailCurrentUser['mail']
        ];

        if (PrivilegeController::hasPrivilege(['privilegeId' => 'use_mail_services', 'userId' => $GLOBALS['id']])) {
            // User's entities emails
            $entities = EntityModel::getWithUserEntities([
                'select' => ['entities.entity_label', 'entities.email', 'entities.entity_id'],
                'where'  => ['users_entities.user_id = ?'],
                'data'   => [$GLOBALS['userId']]
            ]);

            foreach ($entities as $entity) {
                if (!empty($entity['email'])) {
                    $emails[] = [
                        'label' => $entity['entity_label'],
                        'email' => $entity['email']
                    ];
                }
            }

            // Get from XML
            $emailsEntities = CoreConfigModel::getXmlLoaded(['path' => 'modules/sendmail/xml/externalMailsEntities.xml']);

            $userEntities = array_column($entities, 'entity_id');

            if ($emailsEntities != null) {
                foreach ($emailsEntities->externalEntityMail as $entityMail) {
                    $entityId = (string)$entityMail->targetEntityId;

                    if ($entityId == '') {
                        $emails[] = [
                            'label' => (string)$entityMail->defaultName,
                            'email' => (string)$entityMail->EntityMail
                        ];
                    } elseif (in_array($entityId, $userEntities)) {
                        $entityLabel = EntityModel::getByEntityId([
                            'select'   => ['entity_label'],
                            'entityId' => $entityId
                        ]);

                        if (!empty($entityLabel)) {
                            $emails[] = [
                                'label' => $entityLabel['entity_label'],
                                'email' => (string)$entityMail->EntityMail
                            ];
                        }
                    }
                }
            }
        }

        return $response->withJson(['emails' => $emails]);
    }
    public static function getInitializationByResId(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['resId']) || !ResController::hasRightByResId(['resId' => [$args['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $resource = ResModel::getById(['select' => ['filename', 'version', 'alt_identifier', 'subject', 'typist', 'format', 'filesize'], 'resId' => $args['resId']]);
        if (empty($resource)) {
            return $response->withStatus(400)->withJson(['errors' => 'Document does not exist']);
        }

        $document = [];
        if (!empty($resource['filename'])) {
            $convertedResource = AdrModel::getDocuments([
                'select'    => ['docserver_id', 'path', 'filename'],
                'where'     => ['res_id = ?', 'type in (?)', 'version = ?'],
                'data'      => [$args['resId'], ['PDF', 'SIGN'], $resource['version']],
                'orderBy'   => ["type='SIGN' DESC"],
                'limit'     => 1
            ]);
            $convertedDocument = null;
            if (!empty($convertedResource[0])) {
                $docserver = DocserverModel::getByDocserverId(['docserverId' => $convertedResource[0]['docserver_id'], 'select' => ['path_template']]);
                $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $convertedResource[0]['path']) . $convertedResource[0]['filename'];
                if (file_exists($pathToDocument)) {
                    $convertedDocument = [
                        'size'  => StoreController::getFormattedSizeFromBytes(['size' => filesize($pathToDocument)])
                    ];
                }
            }

            $document = [
                'id'                => $args['resId'],
                'chrono'            => $resource['alt_identifier'],
                'label'             => $resource['subject'],
                'convertedDocument' => $convertedDocument,
                'creator'           => UserModel::getLabelledUserById(['id' => $resource['typist']]),
                'format'            => $resource['format'],
                'size'              => StoreController::getFormattedSizeFromBytes(['size' => $resource['filesize']])
            ];
        }

        $attachments = [];
        $attachmentTypes = AttachmentModel::getAttachmentsTypesByXML();
        $rawAttachments = AttachmentModel::get([
            'select'    => ['res_id', 'title', 'identifier', 'attachment_type', 'typist', 'format', 'filesize'],
            'where'     => ['res_id_master = ?', 'attachment_type not in (?)', 'status not in (?)'],
            'data'      => [$args['resId'], ['signed_response'], ['DEL', 'OBS']]
        ]);
        foreach ($rawAttachments as $attachment) {
            $attachmentId = $attachment['res_id'];
            $signedAttachment = AttachmentModel::get([
                'select'    => ['res_id'],
                'where'     => ['origin = ?', 'status != ?', 'attachment_type = ?'],
                'data'      => ["{$attachment['resId']},res_attachments", 'DEL', 'signed_response']
            ]);
            if (!empty($signedAttachment[0])) {
                $attachmentId = $signedAttachment[0]['res_id'];
            }

            $convertedAttachment = AdrModel::getAttachments([
                'select'    => ['docserver_id', 'path', 'filename'],
                'where'     => ['res_id = ?', 'type = ?'],
                'data'      => [$attachmentId, 'PDF'],
            ]);
            $convertedDocument = null;
            if (!empty($convertedAttachment[0])) {
                $docserver = DocserverModel::getByDocserverId(['docserverId' => $convertedAttachment[0]['docserver_id'], 'select' => ['path_template']]);
                $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $convertedAttachment[0]['path']) . $convertedAttachment[0]['filename'];
                if (file_exists($pathToDocument)) {
                    $convertedDocument = [
                        'size'  => StoreController::getFormattedSizeFromBytes(['size' => filesize($pathToDocument)])
                    ];
                }
            }

            $attachments[] = [
                'id'                => $attachment['res_id'],
                'chrono'            => $attachment['identifier'],
                'label'             => $attachment['title'],
                'typeLabel'         => $attachmentTypes[$attachment['attachment_type']]['label'],
                'convertedDocument' => $convertedDocument,
                'creator'           => UserModel::getLabelledUserById(['login' => $attachment['typist']]),
                'format'            => $attachment['format'],
                'size'              => StoreController::getFormattedSizeFromBytes(['size' => $attachment['filesize']])
            ];
        }

        $notes = [];
        $userEntities = EntityModel::getByLogin(['login' => $GLOBALS['userId'], 'select' => ['entity_id']]);
        $userEntities = array_column($userEntities, 'entity_id');
        $rawNotes = NoteModel::get(['select' => ['id', 'note_text', 'user_id'], 'where' => ['identifier = ?'], 'data' => [$args['resId']]]);
        foreach ($rawNotes as $rawNote) {
            $allowed = false;
            if ($rawNote['user_id'] == $GLOBALS['id']) {
                $allowed = true;
            } else {
                $noteEntities = NoteEntityModel::get(['select' => ['item_id'], 'where' => ['note_id = ?'], 'data' => [$rawNote['id']]]);
                if (!empty($noteEntities)) {
                    foreach ($noteEntities as $noteEntity) {
                        if (in_array($noteEntity['item_id'], $userEntities)) {
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
                    'id'        => $rawNote['id'],
                    'label'     => $rawNote['note_text'],
                    'typeLabel' => 'note',
                    'creator'   => UserModel::getLabelledUserById(['id' => $rawNote['user_id']]),
                    'format'    => 'html',
                    'size'      => null
                ];
            }
        }

        return $response->withJson(['resource' => $document, 'attachments' => $attachments, 'notes' => $notes]);
    }

    public static function sendEmail(array $args)
    {
        ValidatorModel::notEmpty($args, ['emailId', 'userId']);
        ValidatorModel::intVal($args, ['emailId', 'userId']);

        $email = EmailModel::getById(['id' => $args['emailId']]);
        $email['sender']        = (array)json_decode($email['sender']);
        $email['recipients']    = array_unique(json_decode($email['recipients']));
        $email['cc']            = array_unique(json_decode($email['cc']));
        $email['cci']           = array_unique(json_decode($email['cci']));

        $hierarchyMail = ['cci' => ['recipients', 'cc'], 'cc' => ['recipients']];
        foreach ($hierarchyMail as $lowEmail => $ahighEmail) {
            foreach ($ahighEmail as $highEmail) {
                foreach ($email[$lowEmail] as $currentKey => $currentEmail) {
                    if (in_array($currentEmail, $email[$highEmail])) {
                        unset($email[$lowEmail][$currentKey]);
                    }
                }
            }
        }

        $configuration = ConfigurationModel::getByService(['service' => 'admin_email_server', 'select' => ['value']]);
        $configuration = (array)json_decode($configuration['value']);
        if (empty($configuration)) {
            return ['errors' => 'Configuration is missing'];
        }

        $user = UserModel::getById(['id' => $args['userId'], 'select' => ['firstname', 'lastname', 'user_id']]);

        $phpmailer = new PHPMailer();

        $emailFrom = empty($configuration['from']) ? $email['sender']['email'] : $configuration['from'];
        if (empty($email['sender']['entityId'])) {
            // Usefull for old sendmail server which doesn't support accent encoding
            $setFrom = TextFormatModel::normalize(['string' => "{$user['firstname']} {$user['lastname']}"]);
            $phpmailer->setFrom($emailFrom, ucwords($setFrom));
        } else {
            $entity = EntityModel::getById(['id' => $email['sender']['entityId'], 'select' => ['short_label']]);
            // Usefull for old sendmail server which doesn't support accent encoding
            $setFrom = TextFormatModel::normalize(['string' => $entity['short_label']]);
            $phpmailer->setFrom($emailFrom, ucwords($setFrom));
        }
        if (in_array($configuration['type'], ['smtp', 'mail'])) {
            if ($configuration['type'] == 'smtp') {
                $phpmailer->isSMTP();
            } elseif ($configuration['type'] == 'mail') {
                $phpmailer->isMail();
            }

            $phpmailer->Host = $configuration['host'];
            $phpmailer->Port = $configuration['port'];
            $phpmailer->SMTPAutoTLS = false;
            if (!empty($configuration['secure'])) {
                $phpmailer->SMTPSecure = $configuration['secure'];
            }
            $phpmailer->SMTPAuth = $configuration['auth'];
            if ($configuration['auth']) {
                $phpmailer->Username = $configuration['user'];
                if (!empty($configuration['password'])) {
                    $phpmailer->Password = PasswordModel::decrypt(['cryptedPassword' => $configuration['password']]);
                }
            }
        } elseif ($configuration['type'] == 'sendmail') {
            $phpmailer->isSendmail();
        } elseif ($configuration['type'] == 'qmail') {
            $phpmailer->isQmail();
        }

        $phpmailer->addReplyTo($email['sender']['email']);
        $phpmailer->CharSet = $configuration['charset'];

        foreach ($email['recipients'] as $recipient) {
            $phpmailer->addAddress($recipient);
        }
        foreach ($email['cc'] as $recipient) {
            $phpmailer->addCC($recipient);
        }
        foreach ($email['cci'] as $recipient) {
            $phpmailer->addBCC($recipient);
        }

        if ($email['is_html']) {
            $phpmailer->isHTML(true);

            $dom = new \DOMDocument();
            $internalErrors = libxml_use_internal_errors(true);
            $dom->loadHTML($email['body'], LIBXML_NOWARNING);
            libxml_use_internal_errors($internalErrors);
            $images = $dom->getElementsByTagName('img');

            foreach ($images as $key => $image) {
                $originalSrc = $image->getAttribute('src');
                if (preg_match('/^data:image\/(\w+);base64,/', $originalSrc)) {
                    $encodedImage = substr($originalSrc, strpos($originalSrc, ',') + 1);
                    $imageFormat = substr($originalSrc, 11, strpos($originalSrc, ';') - 11);
                    $phpmailer->addStringEmbeddedImage(base64_decode($encodedImage), "embeded{$key}", "embeded{$key}.{$imageFormat}");
                    $email['body'] = str_replace($originalSrc, "cid:embeded{$key}", $email['body']);
                }
            }
        }

        $phpmailer->Subject = $email['object'];
        $phpmailer->Body = $email['body'];
        if (empty($email['body'])) {
            $phpmailer->AllowEmpty = true;
        }

        //zip M2M
        if ($email['message_exchange_id']) {
            $messageExchange = MessageExchangeModel::getMessageByIdentifier(['messageId' => $email['message_exchange_id'], 'select' => ['docserver_id','path','filename','fingerprint','reference']]);
            $docserver       = DocserverModel::getByDocserverId(['docserverId' => $messageExchange['docserver_id']]);
            $docserverType   = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id']]);

            $pathDirectory = str_replace('#', DIRECTORY_SEPARATOR, $messageExchange['path']);
            $filePath      = $docserver['path_template'] . $pathDirectory . $messageExchange['filename'];
            $fingerprint   = StoreController::getFingerPrint([
                'filePath' => $filePath,
                'mode'     => $docserverType['fingerprint_mode'],
            ]);

            if ($fingerprint != $messageExchange['fingerprint']) {
                $email['document'] = (array)json_decode($email['document']);
                return ['errors' => 'Pb with fingerprint of document. ResId master : ' . $email['document']['id']];
            }

            if (is_file($filePath)) {
                $fileContent = file_get_contents($filePath);
                if ($fileContent === false) {
                    return ['errors' => 'Document not found on docserver'];
                }

                $title = preg_replace(utf8_decode('@[\\/:*?"<>|]@i'), '_', substr($messageExchange['reference'], 0, 30));

                $phpmailer->addStringAttachment($fileContent, $title . '.zip');
            }
        } else {
            if (!empty($email['document'])) {
                $email['document'] = (array)json_decode($email['document']);
                if ($email['document']['isLinked']) {
                    $encodedDocument = ResController::getEncodedDocument(['resId' => $email['document']['id'], 'original' => $email['document']['original']]);
                    if (empty($encodedDocument['errors'])) {
                        $phpmailer->addStringAttachment(base64_decode($encodedDocument['encodedDocument']), $encodedDocument['fileName']);
                    }
                }
                if (!empty($email['document']['attachments'])) {
                    $email['document']['attachments'] = (array)$email['document']['attachments'];
                    foreach ($email['document']['attachments'] as $attachment) {
                        $attachment = (array)$attachment;
                        $encodedDocument = AttachmentController::getEncodedDocument(['id' => $attachment['id'], 'original' => $attachment['original']]);
                        if (empty($encodedDocument['errors'])) {
                            $phpmailer->addStringAttachment(base64_decode($encodedDocument['encodedDocument']), $encodedDocument['fileName']);
                        }
                    }
                }
                if (!empty($email['document']['notes'])) {
                    $email['document']['notes'] = (array)$email['document']['notes'];
                    $encodedDocument = NoteController::getEncodedPdfByIds(['ids' => $email['document']['notes']]);
                    if (empty($encodedDocument['errors'])) {
                        $phpmailer->addStringAttachment(base64_decode($encodedDocument['encodedDocument']), 'notes.pdf');
                    }
                }
            }
        }

        $phpmailer->Timeout = 30;
        $phpmailer->SMTPDebug = 1;
        $phpmailer->Debugoutput = function ($str) {
            if (strpos($str, 'SMTP ERROR') !== false) {
                HistoryController::add([
                    'tableName'    => 'emails',
                    'recordId'     => 'email',
                    'eventType'    => 'ERROR',
                    'eventId'      => 'sendEmail',
                    'info'         => $str
                ]);
            }
        };

        $isSent = $phpmailer->send();
        if (!$isSent) {
            $history = HistoryModel::get([
                'select'    => ['info'],
                'where'     => ['user_id = ?', 'event_id = ?', 'event_type = ?'],
                'data'      => [$user['user_id'], 'sendEmail', 'ERROR'],
                'orderBy'   => ['event_date DESC'],
                'limit'     => 1
            ]);
            if (!empty($history[0]['info'])) {
                return ['errors' => $history[0]['info']];
            }

            return ['errors' => $phpmailer->ErrorInfo];
        }

        return ['success' => 'success'];
    }

    private static function controlCreateEmail(array $args)
    {
        ValidatorModel::notEmpty($args, ['userId']);
        ValidatorModel::intVal($args, ['userId']);
        ValidatorModel::arrayType($args, ['data']);

        if (!Validator::stringType()->notEmpty()->validate($args['data']['status'])) {
            return ['errors' => 'Data status is not a string or empty', 'code' => 400];
        } elseif ($args['data']['status'] != 'DRAFT' && (!Validator::arrayType()->notEmpty()->validate($args['data']['sender']) || !Validator::stringType()->notEmpty()->validate($args['data']['sender']['email']))) {
            return ['errors' => 'Data sender email is not set', 'code' => 400];
        } elseif ($args['data']['status'] != 'DRAFT' && !Validator::arrayType()->notEmpty()->validate($args['data']['recipients'])) {
            return ['errors' => 'Data recipients is not an array or empty', 'code' => 400];
        } elseif (!Validator::boolType()->validate($args['data']['isHtml'])) {
            return ['errors' => 'Data isHtml is not a boolean or empty', 'code' => 400];
        }

        $user = UserModel::getById(['id' => $args['userId'], 'select' => ['user_id']]);

        if (!empty($args['data']['document'] && !empty($args['data']['document']['id']))) {
            $check = Validator::intVal()->notEmpty()->validate($args['data']['document']['id']);
            $check = $check && Validator::boolType()->validate($args['data']['document']['isLinked']);
            $check = $check && Validator::boolType()->validate($args['data']['document']['original']);
            if (!$check) {
                return ['errors' => 'Data document errors', 'code' => 400];
            }
            if (!ResController::hasRightByResId(['resId' => [$args['data']['document']['id']], 'userId' => $args['userId']])) {
                return ['errors' => 'Document out of perimeter', 'code' => 403];
            }
            if (!empty($args['data']['document']['attachments'])) {
                if (!is_array($args['data']['document']['attachments'])) {
                    return ['errors' => 'Data document[attachments] is not an array', 'code' => 400];
                }
                foreach ($args['data']['document']['attachments'] as $attachment) {
                    $check = Validator::intVal()->notEmpty()->validate($attachment['id']);
                    $check = $check && Validator::boolType()->validate($attachment['original']);
                    if (!$check) {
                        return ['errors' => 'Data document[attachments] errors', 'code' => 400];
                    }
                    $checkAttachment = AttachmentModel::getById(['id' => $attachment['id'], 'select' => ['res_id_master']]);
                    if (empty($checkAttachment) || $checkAttachment['res_id_master'] != $args['data']['document']['id']) {
                        return ['errors' => 'Attachment out of perimeter', 'code' => 403];
                    }
                }
            }
            if (!empty($args['data']['document']['notes'])) {
                if (!is_array($args['data']['document']['notes'])) {
                    return ['errors' => 'Data document[notes] is not an array', 'code' => 400];
                }
                foreach ($args['data']['document']['notes'] as $note) {
                    if (!Validator::intVal()->notEmpty()->validate($note)) {
                        return ['errors' => 'Data document[notes] errors', 'code' => 400];
                    }
                    $checkNote = NoteModel::getById(['id' => $note, 'select' => ['identifier']]);
                    if (empty($checkNote) || $checkNote['identifier'] != $args['data']['document']['id']) {
                        return ['errors' => 'Note out of perimeter', 'code' => 403];
                    }

                    $rawUserEntities = EntityModel::getByLogin(['login' => $user['user_id'], 'select' => ['entity_id']]);
                    $userEntities = [];
                    foreach ($rawUserEntities as $rawUserEntity) {
                        $userEntities[] = $rawUserEntity['entity_id'];
                    }
                    $noteEntities = NoteEntityModel::get(['select' => ['item_id'], 'where' => ['note_id = ?'], 'data' => [$note]]);
                    if (!empty($noteEntities)) {
                        $found = false;
                        foreach ($noteEntities as $noteEntity) {
                            if (in_array($noteEntity['item_id'], $userEntities)) {
                                $found = true;
                            }
                        }
                        if (!$found) {
                            return ['errors' => 'Note out of perimeter', 'code' => 403];
                        }
                    }
                }
            }
        }

        return ['success' => 'success'];
    }
}

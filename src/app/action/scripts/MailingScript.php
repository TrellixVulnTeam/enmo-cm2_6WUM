<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Export Seda Script
 * @author dev@maarch.org
 */

namespace ExportSeda\controllers;

require 'vendor/autoload.php';

use Action\controllers\ExternalSignatoryBookTrait;
use Attachment\controllers\AttachmentController;
use Attachment\models\AttachmentModel;
use SrcCore\controllers\LogsController;
use SrcCore\models\DatabasePDO;
use User\models\UserModel;

// ARGS
// --encodedData : All data encoded in base64

MailingScript::initialize($argv);

class MailingScript
{
    public static function initialize($args)
    {
        if (array_search('--encodedData', $args) > 0) {
            $cmd = array_search('--encodedData', $args);
            $data = json_decode(base64_decode($args[$cmd+1]), true);
        }

        if (!empty($data)) {
            DatabasePDO::reset();
            new DatabasePDO(['customId' => $args['data']['customId']]);
            $GLOBALS['customId'] = $args['data']['customId'];

            $currentUser = UserModel::getById(['id' => $args['data']['userId'], 'select' => ['user_id']]);
            $GLOBALS['login'] = $currentUser['user_id'];
            $GLOBALS['id']    = $args['data']['userId'];

            if ($args['action'] == 'sendExternalSignatoryBookAction') {
                MailingScript::sendExternalSignatoryBookAction($args);
            } elseif ($args['action'] == 'generateMailing') {
                MailingScript::generateMailing($args);
            }
        }
    }

    public static function sendExternalSignatoryBookAction(array $args)
    {
        foreach ($args['data']['resources'] as $resource) {
            $result = ExternalSignatoryBookTrait::sendExternalSignatoryBookAction($resource);

            if (!empty($result['errors'])) {
                LogsController::add([
                    'isTech'    => true,
                    'moduleId'  => 'resource',
                    'level'     => 'ERROR',
                    'tableName' => 'letterbox_coll',
                    'recordId'  => $resource['resId'],
                    'eventType' => "Send to external Signature Book failed : {$result['errors']}",
                    'eventId'   => "resId : {$resource['resId']}"
                ]);
            } elseif (!empty($result['history'])) {
                LogsController::add([
                    'isTech'    => true,
                    'moduleId'  => 'resource',
                    'level'     => 'INFO',
                    'tableName' => 'letterbox_coll',
                    'recordId'  => $resource['resId'],
                    'eventType' => "Send to external Signature Book success : {$result['history']}",
                    'eventId'   => "resId : {$resource['resId']}"
                ]);
            }
        }
    }

    public static function generateMailing(array $args)
    {
        foreach ($args['data']['resources'] as $resource) {
            $where = ['res_id_master = ?', 'status = ?'];
            $data = [$resource['resId'], 'SEND_MASS'];

            if (!empty($resource['inSignatureBook'])) {
                $where[] = 'in_signature_book = ?';
                $data[] = true;
            }

            $attachments = AttachmentModel::get([
                'select'  => ['res_id', 'status'],
                'where'   => $where,
                'data'    => $data
            ]);

            foreach ($attachments as $attachment) {
                $result = AttachmentController::generateMailing(['id' => $attachment['res_id'], 'userId' => $GLOBALS['id']]);

                if (!empty($result['errors'])) {
                    LogsController::add([
                        'isTech'    => true,
                        'moduleId'  => 'resource',
                        'level'     => 'ERROR',
                        'recordId'  => $attachment['res_id'],
                        'eventType' => "Mailing generation failed : {$result['errors']}",
                        'eventId'   => "resId : {$attachment['res_id']}"
                    ]);
                } else {
                    LogsController::add([
                        'isTech'    => true,
                        'moduleId'  => 'resource',
                        'level'     => 'INFO',
                        'tableName' => 'letterbox_coll',
                        'recordId'  => $attachment['res_id'],
                        'eventType' => "Mailing generation success",
                        'eventId'   => "resId : {$attachment['res_id']}"
                    ]);
                }
            }
        }
    }
}

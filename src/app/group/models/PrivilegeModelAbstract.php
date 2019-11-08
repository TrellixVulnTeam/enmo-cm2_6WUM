<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 */

/**
 * @brief Privilege Model Abstract
 * @author dev@maarch.org
 */

namespace Group\models;

use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;
use User\models\UserGroupModel;

abstract class PrivilegeModelAbstract
{
    public static function getByUser(array $args)
    {
        ValidatorModel::notEmpty($args, ['id']);
        ValidatorModel::intVal($args, ['id']);

        $aServices = DatabaseModel::select([
            'select'    => ['usergroups_services.service_id'],
            'table'     => ['usergroup_content, usergroups_services, usergroups'],
            'where'     => ['usergroup_content.group_id = usergroups.id', 'usergroups.group_id = usergroups_services.group_id', 'usergroup_content.user_id = ?'],
            'data'      => [$args['id']]
        ]);

        return $aServices;
    }

    public static function canIndex(array $args)
    {
        ValidatorModel::notEmpty($args, ['userId']);
        ValidatorModel::intVal($args, ['userId']);

        $canIndex = UserGroupModel::getWithGroups([
            'select'    => [1],
            'where'     => ['usergroup_content.user_id = ?', 'usergroups.can_index = ?'],
            'data'      => [$args['userId'], true]
        ]);

        return !empty($canIndex);
    }

    public static function getPrivilegesByGroupId(array $args)
    {
        ValidatorModel::notEmpty($args, ['groupId']);
        ValidatorModel::intVal($args, ['groupId']);

        $privileges = DatabaseModel::select([
            'select'    => ['service_id'],
            'table'     => ['usergroups_services, usergroups'],
            'where'     => ['usergroups_services.group_id = usergroups.group_id', 'usergroups.id = ?'],
            'data'      => [$args['groupId']]
        ]);

        $privileges = array_column($privileges, 'service_id');

        return $privileges;
    }

    public static function addPrivilegeToGroup(array $args) {
        ValidatorModel::notEmpty($args, ['privilegeId', 'groupId']);
        ValidatorModel::stringType($args, ['privilegeId', 'groupId']);

        DatabaseModel::insert([
            'table'     => 'usergroups_services',
            'columnsValues' => [
                'group_id'  => $args['groupId'],
                'service_id'  => $args['privilegeId'],
            ]
        ]);

        return true;
    }

    public static function removePrivilegeToGroup(array $args) {
        ValidatorModel::notEmpty($args, ['privilegeId', 'groupId']);
        ValidatorModel::stringType($args, ['privilegeId', 'groupId']);

        DatabaseModel::delete([
            'table' => 'usergroups_services',
            'where' => ['group_id = ?', 'service_id = ?'],
            'data'  => [$args['groupId'], $args['privilegeId']]
        ]);

        return true;
    }

    public static function groupHasPrivilege(array $args)
    {
        ValidatorModel::notEmpty($args, ['groupId', 'privilegeId']);
        ValidatorModel::stringType($args, ['groupId', 'privilegeId']);

        $service = DatabaseModel::select([
            'select'    => ['group_id', 'service_id'],
            'table'     => ['usergroups_services'],
            'where'     => ['group_id = ?', 'service_id = ?'],
            'data'      => [$args['groupId'], $args['privilegeId']]
        ]);

        return !empty($service);
    }
}

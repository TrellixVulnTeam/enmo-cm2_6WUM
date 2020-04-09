<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Authentication Model
* @author dev@maarch.org
*/

namespace SrcCore\models;

class AuthenticationModel
{
    public static function getPasswordHash($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function authentication(array $args)
    {
        ValidatorModel::notEmpty($args, ['login', 'password']);
        ValidatorModel::stringType($args, ['login', 'password']);

        $aReturn = DatabaseModel::select([
            'select'    => ['password'],
            'table'     => ['users'],
            'where'     => ['lower(user_id) = lower(?)', 'status in (?, ?)', '(locked_until is null OR locked_until < CURRENT_TIMESTAMP)'],
            'data'      => [$args['login'], 'OK', 'ABS']
        ]);

        if (empty($aReturn[0])) {
            return false;
        }

        return password_verify($args['password'], $aReturn[0]['password']);
    }
    
    public static function resetFailedAuthentication(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['userId']);
        ValidatorModel::stringType($aArgs, ['userId']);

        DatabaseModel::update([
            'table'     => 'users',
            'set'       => [
                'failed_authentication' => 0,
                'locked_until'          => null,
            ],
            'where'     => ['lower(user_id) = lower(?)'],
            'data'      => [$aArgs['userId']]
        ]);

        return true;
    }

    public static function increaseFailedAuthentication(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['userId', 'tentatives']);
        ValidatorModel::stringType($aArgs, ['userId']);
        ValidatorModel::intVal($aArgs, ['tentatives']);

        DatabaseModel::update([
            'table'     => 'users',
            'set'       => [
                'failed_authentication' => $aArgs['tentatives']
            ],
            'where'     => ['lower(user_id) = lower(?)'],
            'data'      => [$aArgs['userId']]
        ]);

        return true;
    }

    public static function lockUser(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['userId', 'lockedUntil']);
        ValidatorModel::stringType($aArgs, ['userId']);

        DatabaseModel::update([
            'table' => 'users',
            'set'   => [
                'locked_until'  => date('Y-m-d H:i:s', $aArgs['lockedUntil'])
            ],
            'where' => ['lower(user_id) = lower(?)'],
            'data'  => [$aArgs['userId']]
        ]);

        return true;
    }

    public static function generatePassword()
    {
        $length = rand(50, 70);
        $chars = '1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcefghijklmnopqrstuvwxyz!@$%^*_=+,.?';
        $count = mb_strlen($chars);
        for ($i = 0, $password = ''; $i < $length; $i++) {
            $index = rand(0, $count - 1);
            $password .= mb_substr($chars, $index, 1);
        }

        return $password;
    }
}

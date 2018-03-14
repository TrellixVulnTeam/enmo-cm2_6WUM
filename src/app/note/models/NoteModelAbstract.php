<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
*/

/**
 * @brief Note Model
 * @author dev@maarch.org
 */

namespace Note\models;

use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;

class NoteModelAbstract
{
    public static function countByResId(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['resId', 'userId']);
        ValidatorModel::intVal($aArgs, ['resId']);
        ValidatorModel::stringType($aArgs, ['userId']);

        $nb = 0;
        $countedNotes = [];
        $entities = [];

        $aEntities = DatabaseModel::select([
            'select'    => ['entity_id'],
            'table'     => ['users_entities'],
            'where'     => ['user_id = ?'],
            'data'      => [$aArgs['userId']]
        ]);

        foreach ($aEntities as $value) {
            $entities[] = $value['entity_id'];
        }

        $aNotes = DatabaseModel::select([
            'select'    => ['notes.id', 'user_id', 'item_id'],
            'table'     => ['notes', 'note_entities'],
            'left_join' => ['notes.id = note_entities.note_id'],
            'where'     => ['identifier = ?'],
            'data'      => [$aArgs['resId']]
        ]);

        foreach ($aNotes as $value) {
            if (empty($value['item_id']) && !in_array($value['id'], $countedNotes)) {
                ++$nb;
                $countedNotes[] = $value['id'];
            } elseif (!empty($value['item_id'])) {
                if ($value['user_id'] == $aArgs['userId'] && !in_array($value['id'], $countedNotes)) {
                    ++$nb;
                    $countedNotes[] = $value['id'];
                } elseif (in_array($value['item_id'], $entities) && !in_array($value['id'], $countedNotes)) {
                    ++$nb;
                    $countedNotes[] = $value['id'];
                }
            }
        }

        return $nb;
    }

    public static function create(array $aArgs = [])
    {
        ValidatorModel::notEmpty($aArgs, ['identifier', 'tablename', 'user_id', 'coll_id', 'note_text']);
        ValidatorModel::intVal($aArgs, ['identifier']);

        DatabaseModel::insert([
            'table' => 'notes',
            'columnsValues' => [
                'identifier' => $aArgs['identifier'],
                'tablename'  => $aArgs['tablename'],
                'user_id'    => $aArgs['user_id'],
                'date_note'  => 'CURRENT_TIMESTAMP',
                'note_text'  => $aArgs['note_text'],
                'coll_id'    => $aArgs['coll_id'],
            ]
        ]);

        return true;
    }
}

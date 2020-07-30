<?php

namespace Kraftausdruck\InstagramFeed\Models;

use SilverStripe\ORM\DataObject;

class InstaAuthObj extends DataObject
{
    private static $db = [
        'LongLivedToken' => 'Text',
        'user_id' => 'Varchar(255)'
    ];

    private static $table_name = 'InstaAuthObj';

    private static $default_sort = 'LastEdited DESC';

    private static $summary_fields = [
        'Created',
        'LastEdited',
        'user_id'
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }
}

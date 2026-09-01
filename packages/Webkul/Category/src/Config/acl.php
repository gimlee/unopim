<?php

return [
    [
        'key' => 'catalog.taxonomy',
        'name' => 'category::app.taxonomy.title',
        'route' => 'admin.catalog.taxonomy.index',
        'sort' => 7,
    ],
    [
        'key' => 'catalog.taxonomy.edit',
        'name' => 'admin::app.acl.edit',
        'route' => 'admin.catalog.taxonomy.mappings.store',
        'sort' => 1,
    ],
];

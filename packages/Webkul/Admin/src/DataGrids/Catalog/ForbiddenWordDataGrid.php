<?php

namespace Webkul\Admin\DataGrids\Catalog;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class ForbiddenWordDataGrid extends DataGrid
{
    protected $primaryColumn = 'id';

    protected $sortColumn = 'updated_at';

    protected $sortOrder = 'desc';

    public function prepareQueryBuilder(): Builder
    {
        return DB::table('content_policy_forbidden_words')
            ->select('id', 'term', 'status', 'notes', 'created_at', 'updated_at');
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'term',
            'label'      => '违禁词',
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'status',
            'label'      => '状态',
            'type'       => 'boolean',
            'filterable' => true,
            'sortable'   => true,
            'options'    => [
                'type'   => 'basic',
                'params' => ['options' => [
                    ['label' => '启用', 'value' => 1],
                    ['label' => '停用', 'value' => 0],
                ]],
            ],
            'closure' => fn ($row): string => $row->status
                ? '<span class="label-active">启用</span>'
                : '<span class="label-info">停用</span>',
        ]);

        $this->addColumn([
            'index'      => 'notes',
            'label'      => '备注',
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => false,
        ]);

        $this->addColumn([
            'index'      => 'updated_at',
            'label'      => '更新时间',
            'type'       => 'datetime',
            'filterable' => true,
            'sortable'   => true,
        ]);
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('catalog.forbidden_words.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => '编辑',
                'method' => 'GET',
                'url'    => fn ($row): string => route('admin.catalog.forbidden_words.show', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('catalog.forbidden_words.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => '删除',
                'method' => 'DELETE',
                'url'    => fn ($row): string => route('admin.catalog.forbidden_words.destroy', $row->id),
            ]);
        }
    }
}

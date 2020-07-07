<?php

namespace Ktcd\Datatable\Traits;

trait KTDatatable
{

    public static function scopeDatatable($query, $formatter = null)
    {
        $keywords = isset(request('query')['keywords']) ? request('query')['keywords'] : null;
        $sortField = request()->input('sort.field', static::getDefaultSortField());
        $sort = [
            'field' => in_array($sortField, self::getValidColumns()) ? $sortField : null,
            'sort' => request()->input('sort.sort', static::getDefaultSort())
        ];
        $pagination = request()->get('pagination', [
            'page' => 1,
            'perpage' => 2
        ]);
        request()->merge(['page' => $pagination['page']]);
        $paginate = $query->filterDatatable()
            ->searchDatatable($keywords)
            ->orderByDatatable($sort)
            ->paginate($pagination['perpage']);
        return [
            'meta' => [
                'page' => (int) $paginate->currentPage(),
                'pages' => (int) ceil($paginate->total() / $paginate->perPage()),
                'perpage' => (int) $paginate->perPage(),
                'total' => (int) $paginate->total(),
                'sort' => $sort['sort'],
                'field' => $sort['field'],
            ],
            'data' => is_callable($formatter) ? $formatter($paginate->items()) : static::formatter($paginate->items())
        ];
    }

    public static function scopeFilterDatatable($query)
    {
        return $query;
    }

    public static function scopeSearchDatatable($query, $keyword)
    {
        $fields = static::getSearchableColumns();
        $operator = static::getSearchOperator();
        return $query->when($keyword, function ($query) use ($keyword, $fields, $operator) {
            if ($fields) {
                $query->where(function ($query) use ($keyword, $fields, $operator) {
                    foreach ($fields as $field) {
                        $query->orWhere($field, $operator, "%{$keyword}%");
                    }
                });
            }
        });
    }

    public static function scopeOrderByDatatable($query, $sort)
    {
        if ($sort['field']) {
            return $query->orderBy($sort['field'], $sort['sort']);
        }
        return $query;
    }

    public static function getSearchOperator()
    {
        return 'like';
    }

    public static function getSearchableColumns()
    {
        return [];
    }

    public static function getValidColumns()
    {
        return [];
    }

    public static function getDefaultSortField()
    {
        return null;
    }

    public static function getDefaultSort()
    {
        return 'asc';
    }

    public static function formatter($collections)
    {
        return $collections;
    }
}

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
        $searchables = static::getSearchableColumns();
        return $query->when($keyword, function ($query) use ($keyword, $searchables) {
            if ($searchables) {
                $query->where(function ($query) use ($keyword, $searchables) {
                    function getGrouppedSearchable($field)
                    {
                        $splitted = explode('.', $field);
                        if (count($splitted) > 1) {
                            $key = array_shift($splitted);
                            return [
                                $key => count($splitted) > 1 ? getGrouppedSearchable(implode(
                                    '.',
                                    $splitted
                                )) : [array_shift($splitted)]
                            ];
                        } else {
                            return [array_shift($splitted)];
                        }
                    }
                    
                    $grouppedSearchables = [];
                    foreach ($searchables as $field) {
                        $grouppedSearchables = array_merge_recursive(
                            $grouppedSearchables,
                            getGrouppedSearchable($field)
                        );
                    }

                    function buildGrouppedSearchablesQuery($query, $keyword, $relationship, $fields)
                    {
                        if (is_array($fields)) {
                            $query->orWhereHas($relationship, function ($query) use ($fields, $keyword) {
                                $query->where(function ($query) use ($keyword, $fields) {
                                    foreach ($fields as $key => $field) {
                                        buildGrouppedSearchablesQuery($query, $keyword, $key, $field);
                                    }
                                });
                            });
                        } else {
                            $query->orWhere($fields, 'like', "%{$keyword}%");
                        }
                    }
                    
                    foreach ($grouppedSearchables as $relationship => $fields) {
                        buildGrouppedSearchablesQuery($query, $keyword, $relationship, $fields);
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

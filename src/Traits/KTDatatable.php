<?php

namespace Svknd\Datatable\Traits;

trait KTDatatable
{

    public static function scopeDatatable($query, $params = [], $formatter = null)
    {
        $fields = isset($params['fields']) ? $params['fields'] : [];
        $searchable = isset($params['searchable']) ? $params['searchable'] : [];
        $joins = isset($params['joins']) ? $params['joins'] : [];
        $wheres = isset($params['wheres']) ? $params['wheres'] : [];
        $groups = isset($params['groups']) ? $params['groups'] : [];
        $defaultSort = isset($params['orders']) ? $params['orders'] : [];

        /**
         * start:select query
         */
        $selectFields = [];
        $validFields = [];
        foreach ($fields as $fieldOriginal => $fieldAlias) {
            $selectFields[] = $fieldOriginal . ' as ' . $fieldAlias;
            $validFields[] = $fieldAlias;
        }
        if ($selectFields) {
            $query->select($selectFields);
        }
        /**
         * end:select query
         */

        /**
         * start:join query
         */
        foreach ($joins as $join) {
            $on = $join['on'];
            if (count($on) != count($on, COUNT_RECURSIVE)) {
                /** for multiple join condition */
                $query->{$join['type']}($join['table'], function ($join) use ($on) {
                    foreach ($on as $value) {
                        $join->on($value[0], $value[1], $value[2]);
                    }
                });
            } else {
                if ($join['type'] == 'join') {
                    $query->join($join['table'], function ($join) use ($on) {
                        $join->on($on[0], $on[1], $on[2]);
                    });
                } elseif ($join['type'] == 'leftJoin') {
                    $query->leftJoin($join['table'], function ($join) use ($on) {
                        $join->on($on[0], $on[1], $on[2]);
                    });
                } elseif ($join['type'] == 'leftJoinSub') {
                    $query->leftJoinSub($join['table'], $join['alias'], function ($join) use ($on) {
                        $join->on($on[0], $on[1], $on[2]);
                    });
                }
            }
        }
        /**
         * end:join query
         */

        /**
         * start:sorting query
         */
        $sortField = request()->input('sort.field', null);
        $sort = [
            [
                'field' => in_array($sortField, $validFields) ? $sortField : null,
                'sort' => request()->input('sort.sort', 'asc')
            ]
        ];
        
        if(!empty($defaultSort)){
            $sort = array_merge($sort, $defaultSort);
        }
        
        $query->orderByDatatable($sort);
        /**
         * end:sorting query
         */

        /**
         * start:search query
         */
        $keywords = isset(request('query')['keywords']) ? request('query')['keywords'] : null;
        $query->searchDatatable($keywords, $searchable);
        /**
         * end:search query
         */

        /**
         * start:where query
         */
        $query->filterDatatable($fields);
        /**
         * end:where query
         */

        // custom where condition
        foreach ($wheres as $where) {
            $condition = $where['condition'];
            $query->{$where['type']}($condition[0], $condition[1]);
        }

        // start groupBy query
        if (!empty($groups)) {
            $query->groupBy($groups);
        }

        $pagination = request()->get('pagination', [
            'page' => 1,
            'perpage' => 10
        ]);
        request()->merge(['page' => $pagination['page']]);

        $paginate = $query->paginate($pagination['perpage']);

        return [
            'meta' => [
                'page' => (int) $paginate->currentPage(),
                'pages' => (int) ceil($paginate->total() / $paginate->perPage()),
                'perpage' => (int) $paginate->perPage(),
                'total' => (int) $paginate->total(),
                'sort' => $sort[0]['sort'],
                'field' => $sort[0]['field'],
            ],
            'data' => is_callable($formatter) ? $formatter($paginate->items()) : 
                (class_exists($formatter) ? new $formatter($paginate->items()) : static::formatter($paginate->items()))
        ];
    }

    public static function scopeSearchDatatable($query, $keyword, $params)
    {
        $fields = isset($params['fields']) ? $params['fields'] : [];
        $operator = isset($params['operator']) ? $params['operator'] : 'ilike';
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

    public static function scopeOrderByDatatable($query, $sorts)
    {
        foreach($sorts as $key => $sort){
            if ($sort['field']) {
                 $query->orderBy($sort['field'], $sort['sort']);
            }
        }
        return $query;
    }

    public static function scopeFilterDatatable($query, $fields)
    {

        /**
         * start:where query
         */
        if (request('query') ) {
            foreach (request('query') as $field => $value) {
                if (in_array($field, $fields)) {
                    if (is_array(request('query')[$field])) {
                        $query->whereIn(array_search($field, $fields), $value);
                    } else {
                        if($value[0] == '%' || $value[strlen($value) - 1] == '%') {
                            $operator = isset($params['operator']) ? $params['operator'] : 'ilike';
                            $isField = array_search($field, $fields);
                            $value = str_replace('%25', '%', urlencode($value));

                            $query->where($isField, $operator, $value);
                        }
                        else if ($value == 'is_null') {
                            $query->whereNull(array_search($field, $fields));
                        } else {
                            $query->where(array_search($field, $fields), $value);
                        }
                    }
                }
            }
        }
        /**
         * end:where query
         */
    }

    public static function formatter($collections)
    {
        return $collections;
    }
}

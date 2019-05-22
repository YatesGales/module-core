<?php
/**
 * Created by PhpStorm.
 * User: Govern Fu
 * Date: 2019/3/11
 * Time: 上午10:11
 */

namespace Modules\Core\Criteria;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;

class RequestCriteria implements CriteriaInterface
{
    /**
     * @var \Illuminate\Http\Request
     */
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Apply criteria in query repository
     *
     * @param Builder|Model $model
     * @param RepositoryInterface $repository
     *
     * @return mixed
     * @throws \Exception
     */
    public function apply($model, RepositoryInterface $repository)
    {
        $fieldsSearchable = $repository->getFieldsSearchable();
        $search = $this->request->get(config('repository.criteria.params.search', 'search'), null);
        $searchFields = $this->request->get(config('repository.criteria.params.searchFields', 'searchFields'), null);
        $filter = $this->request->get(config('repository.criteria.params.filter', 'filter'), null);
        $orderBy = $this->request->get(config('repository.criteria.params.orderBy', 'orderBy'), null);
        $sortedBy = $this->request->get(config('repository.criteria.params.sortedBy', 'sortedBy'), 'asc');
        $with = $this->request->get(config('repository.criteria.params.with', 'with'), null);
        $searchJoin = $this->request->get(config('repository.criteria.params.searchJoin', 'searchJoin'), null);
        $sortedBy = !empty($sortedBy) ? $sortedBy : 'asc';

        if ($search && is_array($fieldsSearchable) && count($fieldsSearchable)) {

            $searchFields =
                is_array($searchFields) || is_null($searchFields) ? $searchFields : explode(';', $searchFields);
            $fields = $this->parserFieldsSearch($fieldsSearchable, $searchFields);
            $isFirstField = true;
            $searchData = $this->parserSearchData($search);
            $search = $this->parserSearchValue($search);
            $modelForceAndWhere = strtolower($searchJoin) === 'and';

            $model =
                $model->where(function ($query) use (
                    $fields,
                    $search,
                    $searchData,
                    $isFirstField,
                    $modelForceAndWhere
                ) {
                    /** @var Builder $query */

                    foreach ($fields as $field => $condition) {

                        if (is_numeric($field)) {
                            $field = $condition;
                            $condition = "=";
                        }

                        $value = null;

                        $condition = trim(strtolower($condition));

                        if (isset($searchData[$field])) {
                            $value = $searchData[$field];
                            if ($condition == "like" || $condition == "ilike") {
                                $value = "%{$value}%";
                            }
                            if ($condition == "in" || $condition == "between" || $condition == "cross") {
                                $value = explode(',', $value);
                            }
                        } else {
                            if (!is_null($search)) {
                                $value = $search;
                                if ($condition == "like" || $condition == "ilike") {
                                    $value = "%{$value}%";
                                }
                                if ($condition == "in" || $condition == "between" || $condition == "cross") {
                                    $value = explode(',', $value);
                                }
                            }
                        }

                        $relation = null;
                        if (stripos($field, '.')) {
                            $explode = explode('.', $field);
                            $field = array_pop($explode);
                            $relation = implode('.', $explode);
                        }
                        $modelTableName = $query->getModel()->getTable();
                        if ($isFirstField || $modelForceAndWhere) {
                            if (!is_null($value)) {
                                if (!is_null($relation)) {
                                    $query->whereHas($relation, function (Builder $query) use (
                                        $field,
                                        $condition,
                                        $value
                                    ) {
                                        if ($condition == 'in') {
                                            $query->whereIn($field, $value);
                                        } elseif ($condition == 'between') {
                                            $query->whereBetween($field, $value);
                                        } elseif ($condition == 'cross') {
                                            $query->where(function (Builder $query) use ($field, $value) {
                                                $query->where(function (Builder $query) use ($field, $value) {
                                                    $query->where("{$field}_min", '<=', $value[0])
                                                          ->where("{$field}_max", '>=', $value[1]);
                                                })->orWhere(function (Builder $query) use ($field, $value) {
                                                    $query->where("{$field}_min", '<=', $value[0])
                                                          ->where("{$field}_max", '>=', $value[0]);
                                                })->orWhere(function (Builder $query) use ($field, $value) {
                                                    $query->where("{$field}_min", '>=', $value[0])
                                                          ->where("{$field}_max", '<=', $value[1]);
                                                })->orWhere(function (Builder $query) use ($field, $value) {
                                                    $query->where("{$field}_min", '>=', $value[0])
                                                          ->where("{$field}_max", '>=', $value[1])
                                                          ->where("{$field}_min", '<=', $value[1]);
                                                });
                                            });
                                        } else {
                                            $query->where($field, $condition, $value);
                                        }
                                    });
                                } else {
                                    if ($condition == 'in') {
                                        $query->whereIn($field, $value);
                                    } elseif ($condition == 'between') {
                                        $query->whereBetween($field, $value);
                                    } elseif ($condition == 'cross') {
                                        $query->where(function (Builder $query) use ($field, $value) {
                                            $query->where(function (Builder $query) use ($field, $value) {
                                                $query->where("{$field}_min", '<=', $value[0])
                                                      ->where("{$field}_max", '>=', $value[1]);
                                            })->orWhere(function (Builder $query) use ($field, $value) {
                                                $query->where("{$field}_min", '<=', $value[0])
                                                      ->where("{$field}_max", '>=', $value[0]);
                                            })->orWhere(function (Builder $query) use ($field, $value) {
                                                $query->where("{$field}_min", '>=', $value[0])
                                                      ->where("{$field}_max", '<=', $value[1]);
                                            })->orWhere(function (Builder $query) use ($field, $value) {
                                                $query->where("{$field}_min", '>=', $value[0])
                                                      ->where("{$field}_max", '>=', $value[1])
                                                      ->where("{$field}_min", '<=', $value[1]);
                                            });
                                        });
                                    } else {
                                        $query->where($field, $condition, $value);
                                    }
                                }
                                $isFirstField = false;
                            }
                        } else {
                            if (!is_null($value)) {
                                if (!is_null($relation)) {
                                    $query->orWhereHas($relation, function (Builder $query) use (
                                        $field,
                                        $condition,
                                        $value
                                    ) {
                                        if ($condition == 'in') {
                                            $query->whereIn($field, $value);
                                        } elseif ($condition == 'between') {
                                            $query->whereBetween($field, $value);
                                        } elseif ($condition == 'cross') {
                                            $query->where(function (Builder $query) use ($field, $value) {
                                                $query->where(function (Builder $query) use ($field, $value) {
                                                    $query->where("{$field}_min", '<=', $value[0])
                                                          ->where("{$field}_max", '>=', $value[1]);
                                                })->orWhere(function (Builder $query) use ($field, $value) {
                                                    $query->where("{$field}_min", '<=', $value[0])
                                                          ->where("{$field}_max", '>=', $value[0]);
                                                })->orWhere(function (Builder $query) use ($field, $value) {
                                                    $query->where("{$field}_min", '>=', $value[0])
                                                          ->where("{$field}_max", '<=', $value[1]);
                                                })->orWhere(function (Builder $query) use ($field, $value) {
                                                    $query->where("{$field}_min", '>=', $value[0])
                                                          ->where("{$field}_max", '>=', $value[1])
                                                          ->where("{$field}_min", '<=', $value[1]);
                                                });
                                            });
                                        } else {
                                            $query->where($field, $condition, $value);
                                        }
                                    });
                                } else {
                                    if ($condition == 'in') {
                                        $query->orWhereIn($modelTableName.'.'.$field, $value);
                                    } elseif ($condition == 'between') {
                                        $query->orWhereBetween($modelTableName.'.'.$field, $value);
                                    } elseif ($condition == 'cross') {
                                        $query->orWhere(function (Builder $query) use ($field, $value) {
                                            $query->where(function (Builder $query) use ($field, $value) {
                                                $query->where("{$field}_min", '<=', $value[0])
                                                      ->where("{$field}_max", '>=', $value[1]);
                                            })->orWhere(function (Builder $query) use ($field, $value) {
                                                $query->where("{$field}_min", '<=', $value[0])
                                                      ->where("{$field}_max", '>=', $value[0]);
                                            })->orWhere(function (Builder $query) use ($field, $value) {
                                                $query->where("{$field}_min", '>=', $value[0])
                                                      ->where("{$field}_max", '<=', $value[1]);
                                            })->orWhere(function (Builder $query) use ($field, $value) {
                                                $query->where("{$field}_min", '>=', $value[0])
                                                      ->where("{$field}_max", '>=', $value[1])
                                                      ->where("{$field}_min", '<=', $value[1]);
                                            });
                                        });
                                    } else {
                                        $query->orWhere($modelTableName.'.'.$field, $condition, $value);
                                    }
                                }
                            }
                        }
                    }
                });
        }

        if (isset($orderBy) && !empty($orderBy)) {
            $split = explode('|', $orderBy);
            if (count($split) > 1) {
                /*
                 * ex.
                 * products|description -> join products on current_table.product_id = products.id order by description
                 *
                 * products:custom_id|products.description -> join products on current_table.custom_id = products.id order
                 * by products.description (in case both tables have same column name)
                 */
                $table = $model->getModel()->getTable();
                $sortTable = $split[0];
                $sortColumn = $split[1];

                $split = explode(':', $sortTable);
                if (count($split) > 1) {
                    $sortTable = $split[0];
                    $keyName = $table.'.'.$split[1];
                } else {
                    /*
                     * If you do not define which column to use as a joining column on current table, it will
                     * use a singular of a join table appended with _id
                     *
                     * ex.
                     * products -> product_id
                     */
                    $prefix = Str::singular($sortTable);
                    $keyName = $table.'.'.$prefix.'_id';
                }

                $model = $model
                    ->leftJoin($sortTable, $keyName, '=', $sortTable.'.id')
                    ->orderBy($sortColumn, $sortedBy)
                    ->addSelect($table.'.*');
            } else {
                $model = $model->orderBy($orderBy, $sortedBy);
            }
        }

        if (isset($filter) && !empty($filter)) {
            if (is_string($filter)) {
                $filter = explode(';', $filter);
            }

            $model = $model->select($filter);
        }

        if ($with) {
            $with = explode(';', $with);
            $model = $model->with($with);
        }

        return $model;
    }

    /**
     * @param $search
     *
     * @return array
     */
    protected function parserSearchData($search)
    {
        $searchData = [];

        if (stripos($search, ':')) {
            $fields = explode(';', $search);

            foreach ($fields as $row) {
                try {
                    list($field, $value) = explode(':', $row);
                    $searchData[$field] = $value;
                } catch (Exception $e) {
                    //Surround offset error
                }
            }
        }

        return $searchData;
    }

    /**
     * @param $search
     * @return string|null
     */
    protected function parserSearchValue($search)
    {

        if (stripos($search, ';') || stripos($search, ':')) {
            $values = explode(';', $search);
            foreach ($values as $value) {
                $s = explode(':', $value);
                if (count($s) == 1) {
                    return $s[0];
                }
            }

            return null;
        }

        return $search;
    }

    /**
     * @param array $fields
     * @param array|null $searchFields
     * @return array
     * @throws \Exception
     */
    protected function parserFieldsSearch(array $fields = [], array $searchFields = null)
    {
        if (!is_null($searchFields) && count($searchFields)) {
            $acceptedConditions = config('repository.criteria.acceptedConditions', [
                '=',
                'like',
            ]);
            $originalFields = $fields;
            $fields = [];

            foreach ($searchFields as $index => $field) {
                $field_parts = explode(':', $field);
                $temporaryIndex = array_search($field_parts[0], $originalFields);

                if (count($field_parts) == 2) {
                    if (in_array($field_parts[1], $acceptedConditions)) {
                        unset($originalFields[$temporaryIndex]);
                        $field = $field_parts[0];
                        $condition = $field_parts[1];
                        $originalFields[$field] = $condition;
                        $searchFields[$index] = $field;
                    }
                }
            }

            foreach ($originalFields as $field => $condition) {
                if (is_numeric($field)) {
                    $field = $condition;
                    $condition = "=";
                }
                if (in_array($field, $searchFields)) {
                    $fields[$field] = $condition;
                }
            }

            if (count($fields) == 0) {
                throw new Exception((string) trans('repository::criteria.fields_not_accepted', ['field' => implode(',', $searchFields)]));
            }

        }

        return $fields;
    }
}

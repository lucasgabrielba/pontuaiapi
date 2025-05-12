<?php

namespace Domains\Shared\Traits;

trait FiltersNullValues
{
    /**
     * Transform the model instance to an array and filter out null values.
     *
     * @return array
     */
    public function toArray()
    {
        $array = parent::toArray();

        // Remove null values
        return array_filter($array, function ($value) {
            return ! is_null($value);
        });
    }
}

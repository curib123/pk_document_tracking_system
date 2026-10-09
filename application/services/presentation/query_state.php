<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** One normalized GET state for every server-rendered register. */
class Query_state
{
    public static function parse(array $query, $defaultSort = 'created_at')
    {
        $text = static function ($key, $default = '') use ($query) {
            $value=$query[$key]??$default;
            if (!is_scalar($value)) throw new DomainException('Invalid filter value.');
            return trim((string)$value);
        };
        $number = static function ($key) use ($text) {
            $value=$text($key);
            if ($value!=='' && !ctype_digit($value)) throw new DomainException('Invalid selection.');
            return (int)$value;
        };
        $dates=[];
        foreach (['from','to'] as $key) {
            $value=$text($key);
            if ($value!=='') {
                $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
                if (!$parsed || $parsed->format('Y-m-d')!==$value || (int)substr($value,0,4)<1000) {
                    throw new DomainException('Use a valid date in YYYY-MM-DD format.');
                }
            }
            $dates[$key]=$value;
        }
        if ($dates['from']!=='' && $dates['to']!=='' && $dates['from']>$dates['to']) {
            throw new DomainException('Start date must be on or before the end date.');
        }
        $limit=(int)$text('limit');
        return $dates+[
            'q'=>mb_substr($text('q'),0,100), 'status'=>mb_substr($text('status'),0,40),
            'page'=>max(1,min(1000000,(int)$text('page'))),
            'limit'=>in_array($limit,[10,25,50,100],TRUE)?$limit:10,
            'sort'=>mb_substr($text('sort',$defaultSort),0,40),
            'dir'=>strtolower($text('dir',str_ends_with($defaultSort,'_at')?'desc':'asc'))==='desc'?'DESC':'ASC',
            'layout'=>$text('layout')==='grid'?'grid':'table',
            'owner'=>$number('owner'),'role'=>$number('role'),
            'parent'=>$number('parent'),
            'type'=>mb_substr($text('type'),0,50),
            'publication'=>mb_substr($text('publication'),0,20)
        ];
    }
}

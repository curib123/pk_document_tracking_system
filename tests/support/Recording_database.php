<?php
declare(strict_types=1);
/**
 * TEST ONLY: a small connection/result double. Not the CI3 compiler or a MySQL server.
 * It exercises application transaction ownership, error handling, row typing and
 * the Query Builder calls made by models. Production never loads this file.
 */
class CI_Model { public function __construct() {} }
class CI_DB_result
{
    public function __construct(private array $rows=[],private array $types=[]) {}
    public function result_array(): array { return $this->rows; }
    public function field_data(): array { return array_map(fn($name)=>(object)['name'=>$name,'type'=>$this->types[$name]],array_keys($this->types)); }
    public function free_result(): void {}
}
class CI_DB_query_builder
{
    public string $dbdriver='mysqli';
    public bool $db_debug=false;
    public array $events=[];
    public array $queries=[];
    public array $queued=[];
    public array $lastError=['code'=>0,'message'=>''];
    public bool $healthy=true;
    public bool $strict=false;
    public mixed $writeResult=true;
    private array $state=[];
    public function trans_strict($value=true): void { $this->strict=$value; }
    public function trans_begin(): bool { $this->events[]='begin';return true; }
    public function trans_commit(): bool { $this->events[]='commit';return true; }
    public function trans_rollback(): bool { $this->events[]='rollback';return true; }
    public function trans_status(): bool { return $this->healthy; }
    public function trans_complete(): bool { $this->trans_rollback();$this->healthy=true;return false; }
    public function error(): array { return $this->lastError; }
    public function reset_query(): self { $this->state=[];return $this; }
    public function escape(mixed $value): string {
        if($value===null)return 'NULL';if(is_int($value)||is_float($value))return (string)$value;
        return "'".str_replace("'","''",(string)$value)."'";
    }
    public function select($select='*',$escape=null): self { $this->state['select'][]=$select;return $this; }
    public function from($table): self { $this->state['from']=$table;return $this; }
    public function distinct($value=true): self { $this->state['distinct']=$value;return $this; }
    public function join($table,$condition,$type='',$escape=null): self { $this->state['join'][]="JOIN $table ON $condition";return $this; }
    public function where($key,$value=null,$escape=null): self {
        $operator=preg_match('/(<|>|!|=|\s)/',trim($key));
        $condition=$value===null?($key.($operator?'':' IS NULL')):($key.($operator?' ':' = ').$this->escape($value));
        $this->state['where'][]=$condition;return $this;
    }
    public function or_where($key,$value=null,$escape=null): self { return $this->where($key,$value,$escape); }
    public function where_in($key,$values): self { $this->state['where'][]=$key.' IN ('.implode(',',array_map($this->escape(...),$values)).')';return $this; }
    public function group_start(): self { $this->state['where'][]='(';return $this; }
    public function or_group_start(): self { return $this->group_start(); }
    public function group_end(): self { $this->state['where'][]=')';return $this; }
    public function like($key,$value,$side='both',$escape=null): self { $this->state['where'][]=$key.' LIKE '.$this->escape('%'.str_replace(['!','%','_'],['!!','!%','!_'],$value).'%');return $this; }
    public function or_like($key,$value,$side='both',$escape=null): self { return $this->like($key,$value,$side,$escape); }
    public function order_by($column,$direction='',$escape=null): self { $this->state['order'][]="$column $direction";return $this; }
    public function group_by($column): self { $this->state['group'][]=$column;return $this; }
    public function limit($length,$offset=0): self { $this->state['limit']=[$length,$offset];return $this; }
    public function set($key,$value='',$escape=null): self { if(is_array($key))$this->state['set']=array_replace($this->state['set']??[],$key);else $this->state['set'][$key]=$value;return $this; }
    public function get_compiled_select($table='',$reset=true): string {
        if($table!=='')$this->from($table);
        $s='SELECT '.implode(',',$this->state['select']??['*']).' FROM '.($this->state['from']??'');
        $s.=' '.implode(' ',$this->state['join']??[]);
        if(isset($this->state['where']))$s.=' WHERE '.implode(' AND ',$this->state['where']);
        if(isset($this->state['order']))$s.=' ORDER BY '.implode(',',$this->state['order']);
        if(isset($this->state['limit']))$s.=' LIMIT '.$this->state['limit'][0].' OFFSET '.$this->state['limit'][1];
        if($reset)$this->reset_query();return trim($s);
    }
    public function get_compiled_insert($table='',$reset=true): string {
        $data=$this->state['set']??[];
        $s='INSERT INTO '.$table.' ('.implode(',',array_keys($data)).') VALUES ('.implode(',',array_map($this->escape(...),$data)).')';
        if($reset)$this->reset_query();return $s;
    }
    public function query($sql,$binds=[]): mixed { $this->queries[]=[$sql,$binds];$result=array_shift($this->queued)??new CI_DB_result();if($result===false)$this->healthy=false;return $result; }
    public function get($table=''): mixed { return $this->query($this->get_compiled_select($table)); }
    public function insert($table,$data=[]): mixed { $this->set($data);$this->queries[]=['insert',$table,$this->state];$this->reset_query();if($this->writeResult===false)$this->healthy=false;return $this->writeResult; }
    public function update($table,$data=[]): mixed { $this->set($data);$this->queries[]=['update',$table,$this->state];$this->reset_query();if($this->writeResult===false)$this->healthy=false;return $this->writeResult; }
    public function delete($table): mixed { $this->queries[]=['delete',$table,$this->state];$this->reset_query();if($this->writeResult===false)$this->healthy=false;return $this->writeResult; }
    public function insert_id(): int { return 18; }
}

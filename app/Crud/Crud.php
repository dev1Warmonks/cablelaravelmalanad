<?php

namespace App\Crud;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Replacement for Grocery_CRUD 1.6.1 (bootstrap-v4 theme). Same HTML, same JS/CSS files, same URL
 * states (list, add, edit, delete, insert, update, ajax_list, ajax_list_info, insert_validation,
 * update_validation, success, read, clone, delete_multiple, export, print) and the same JSON replies,
 * so the existing theme JavaScript keeps working unchanged.
 *
 * Differences (security): every query uses bound parameters; column names coming from the browser
 * (search/order) are whitelisted; edit/update/delete/read/clone are scoped by the same where() as the
 * list (the old library only scoped the list); only fields shown on the form can be written from POST;
 * output is HTML-escaped.
 *
 * Not yet implemented (throws): set_relation_n_n, upload fields, date/datetime inputs, text editor.
 */
class Crud
{
    public const STATES = ['list', 'add', 'edit', 'delete', 'insert', 'update', 'ajax_list', 'ajax_list_info',
        'insert_validation', 'update_validation', 'success', 'export', 'print', 'read', 'delete_multiple', 'clone',
        'upload_file', 'delete_file'];

    private const ASSETS = 'assets/grocery_crud';

    private const THEME = 'assets/grocery_crud/themes/bootstrap-v4';

    private const JQUERY = 'jquery-1.11.1.min.js';

    private static array $lang;

    public string $table;

    public string $subject = '';

    public string $subjectPlural = '';

    private array $where = [];

    private array $orderBy = [];

    private array $columnList = [];

    private array $displayAs = [];

    private array $required = [];

    private array $relations = [];

    private array $typeOverrides = [];

    private array $callbackColumn = [];

    private array $callbackAddField = [];

    private array $callbackEditField = [];

    private ?Closure $beforeInsert = null;

    private ?Closure $afterInsert = null;

    private ?Closure $beforeUpdate = null;

    private array $unset = ['add' => false, 'edit' => false, 'delete' => false, 'read' => false, 'clone' => false,
        'export' => false, 'print' => false, 'back_to_list' => false];

    private ?string $pkOverride = null;

    private ?array $meta = null;

    // output assembly
    private array $css = [];

    private array $js = [];

    private string $views = '';

    private function __construct(private Request $request, private string $basePath)
    {
        self::$lang ??= require __DIR__.'/lang.php';
    }

    public static function make(Request $request, string $basePath): self
    {
        return new self($request, trim($basePath, '/'));
    }

    // ------------------------------------------------------------------ configuration API (same names, camelCase)

    public function table(string $t): self
    {
        $this->table = $t;

        return $this;
    }

    public function subject(string $s, ?string $plural = null): self
    {
        $this->subject = $s;
        $this->subjectPlural = $plural ?? $s;

        return $this;
    }

    public function primaryKey(string $pk): self
    {
        $this->pkOverride = $pk;

        return $this;
    }

    public function where(string $column, $value, string $op = '='): self
    {
        $this->where[] = [preg_replace('/^'.preg_quote($this->table ?? '', '/')."\./", '', $column), $value, null, $op];

        return $this;
    }

    public function whereNot(string $column, $value): self
    {
        return $this->where($column, $value, '!=');
    }

    /** where column IN (list); an empty list matches nothing. */
    public function whereIn(string $column, array $values): self
    {
        return $this->where($column, array_values($values), 'IN');
    }

    /** Scope rows through a relation: field IN (SELECT pk FROM related WHERE col = val). Old code relied on the unqualified column resolving to the joined table. */
    public function whereVia(string $field, string $col, $value): self
    {
        $this->where[] = [$field, $value, $col];

        return $this;
    }

    public function orderBy(string $column, string $dir = 'asc'): self
    {
        $this->orderBy = [$column, strtolower($dir) === 'desc' ? 'desc' : 'asc'];

        return $this;
    }

    public function columns(string ...$c): self
    {
        $this->columnList = $c;

        return $this;
    }

    public function displayAs(string $field, string $label): self
    {
        $this->displayAs[$field] = $label;

        return $this;
    }

    public function requiredFields(string ...$f): self
    {
        $this->required = $f;

        return $this;
    }

    /** Rates / amounts that must not be negative (every numeric column is already checked to be a number). */
    private array $nonNegative = [];

    public function nonNegative(string ...$f): self
    {
        $this->nonNegative = $f;

        return $this;
    }

    public function changeFieldType(string $field, string $type, $extras = null): self
    {
        $this->typeOverrides[$field] = [$type, $extras];

        return $this;
    }

    public function fieldType(string $field, string $type, $extras = null): self
    {
        return $this->changeFieldType($field, $type, $extras);
    }

    public function callbackColumn(string $c, Closure $cb): self
    {
        $this->callbackColumn[$c] = $cb;

        return $this;
    }

    public function callbackAddField(string $f, Closure $cb): self
    {
        $this->callbackAddField[$f] = $cb;

        return $this;
    }

    public function callbackEditField(string $f, Closure $cb): self
    {
        $this->callbackEditField[$f] = $cb;

        return $this;
    }

    public function callbackBeforeInsert(Closure $cb): self
    {
        $this->beforeInsert = $cb;

        return $this;
    }

    public function callbackAfterInsert(Closure $cb): self
    {
        $this->afterInsert = $cb;

        return $this;
    }

    public function callbackBeforeUpdate(Closure $cb): self
    {
        $this->beforeUpdate = $cb;

        return $this;
    }

    public function unsetAdd(): self
    {
        $this->unset['add'] = $this->unset['clone'] = true;

        return $this;
    }

    public function unsetEdit(): self
    {
        $this->unset['edit'] = true;

        return $this;
    }

    public function unsetDelete(): self
    {
        $this->unset['delete'] = true;

        return $this;
    }

    public function unsetRead(): self
    {
        $this->unset['read'] = true;

        return $this;
    }

    public function unsetClone(): self
    {
        $this->unset['clone'] = true;

        return $this;
    }

    public function unsetExport(): self
    {
        $this->unset['export'] = true;

        return $this;
    }

    public function unsetPrint(): self
    {
        $this->unset['print'] = true;

        return $this;
    }

    public function unsetBackToList(): self
    {
        $this->unset['back_to_list'] = true;

        return $this;
    }

    /** set_relation($field, $table, $titleField, $whereClause = [col => val], $orderBy = null) */
    private array $nn = [];

    private array $uploads = [];

    private array $noEditor = [];

    /** unset_texteditor(...): these text fields get a plain textarea instead of CKEditor. */
    public function unsetTexteditor(string ...$f): self
    {
        $this->noEditor = array_merge($this->noEditor, $f);

        return $this;
    }

    private ?array $fieldsAdd = null;

    private ?array $fieldsEdit = null;

    private array $uniqueFields = [];

    private array $rules = [];

    /** fields([...]) / add_fields / edit_fields: explicit form fields (may include non-table "virtual" fields shown through callbacks). */
    public function fields(array $add, ?array $edit = null): self
    {
        $this->fieldsAdd = $add;
        $this->fieldsEdit = $edit ?? $add;

        return $this;
    }

    public function uniqueFields(string ...$f): self
    {
        $this->uniqueFields = $f;

        return $this;
    }

    /** set_rules($field, $label, 'trim|required'): only trim and required are supported. */
    public function rules(string $field, string $label, string $rules): self
    {
        $this->rules[$field] = [$label, explode('|', $rules)];

        return $this;
    }

    /**
     * set_field_upload($field, $dir, $allowed): file column. Hardened vs the old library: only the listed extensions,
     * image files are verified with getimagesize(), stored under a random name, and deletion is limited to files
     * this screen references (or that this session just uploaded).
     */
    public function uploadField(string $field, string $dir = 'assets/uploads/files', array $allowed = ['gif', 'jpeg', 'jpg', 'png'], int $maxBytes = 2097152): self
    {
        $dir = trim($dir, '/');
        abort_unless(is_dir(public_path($dir)), 500, "Upload folder $dir does not exist");
        $this->uploads[$field] = (object) ['field' => $field, 'path' => $dir, 'allowed' => array_map('strtolower', $allowed), 'max' => $maxBytes];

        return $this;
    }

    /** set_relation_n_n($field, $relationTable, $selectionTable, $pkThis, $pkSel, $titleTemplate, $priority = null, where = Closure(query)) */
    public function relationNN(string $field, string $relationTable, string $selectionTable, string $pkThis, string $pkSel, string $title, ?Closure $where = null): self
    {
        $this->nn[$field] = (object) compact('field', 'relationTable', 'selectionTable', 'pkThis', 'pkSel', 'title', 'where');

        return $this;
    }

    public function relation(string $field, string $table, string $title, array|Closure $where = [], ?string $orderBy = null): self
    {
        $this->relations[$field] = compact('field', 'table', 'title', 'where', 'orderBy');

        return $this;
    }

    // ------------------------------------------------------------------ dispatcher

    /** @return Response|JsonResponse|array{output:string,css_files:array,js_files:array} */
    public function run(?string $state, ?string $param)
    {
        $state = $state === null || $state === '' ? 'list' : $state;
        if (! in_array($state, self::STATES, true)) {
            abort(404);
        }
        $this->guard($state);

        return match ($state) {
            'list', 'success' => $this->showList($state === 'success' ? $param : null),
            'ajax_list' => $this->ajaxList(),
            'ajax_list_info' => response()->json(['total_results' => (int) $this->countQuery($this->filtered())]),
            'add' => $this->showForm('add'),
            'clone' => $this->showForm('clone', $param),
            'edit' => $this->showForm('edit', $param),
            'read' => $this->showForm('read', $param),
            'insert_validation' => $this->validation(),
            'update_validation' => $this->validation($param),
            'insert' => $this->insert(),
            'update' => $this->update($param),
            'delete' => $this->deleteResponse([$param]),
            'delete_multiple' => $this->deleteResponse((array) $this->request->input('ids', [])),
            'export' => $this->export(),
            'print' => $this->printPage(),
            'upload_file' => $this->uploadFile((string) $param),
            'delete_file' => $this->deleteFile((string) $param, (string) $this->request->route('p2')),
        };
    }

    private function guard(string $state): void
    {
        if (in_array($state, ['upload_file', 'delete_file'], true) && $this->unset['add'] && $this->unset['edit']) {
            abort(403);
        }
        $map = ['add' => 'add', 'insert' => 'add', 'edit' => 'edit', 'update' => 'edit', 'delete' => 'delete',
            'delete_multiple' => 'delete', 'read' => 'read', 'clone' => 'clone', 'export' => 'export', 'print' => 'print'];
        if (isset($map[$state]) && $this->unset[$map[$state]]) {
            abort(403);   // old library threw "You don't have permissions for this operation"
        }
        if (in_array($state, ['edit', 'update', 'read', 'clone', 'delete'], true) && ($p = $this->request->route('p1')) !== null && ! is_scalar($p)) {
            abort(404);
        }
    }

    // ------------------------------------------------------------------ metadata

    private function meta(): array
    {
        if ($this->meta !== null) {
            return $this->meta;
        }
        $cols = [];
        foreach (DB::select('SHOW COLUMNS FROM `'.str_replace('`', '', $this->table).'`') as $c) {
            $type = strtolower($c->Type);
            preg_match('/^([a-z]+)(?:\((.+?)\))?/', $type, $m);
            $base = $m[1];
            $len = $m[2] ?? null;
            $defaultLen = ['int' => 11, 'tinyint' => 4, 'smallint' => 6, 'mediumint' => 9, 'bigint' => 20];
            $cols[$c->Field] = (object) [
                'name' => $c->Field,
                'db_type' => $base,
                'db_max_length' => $len ?? ($defaultLen[$base] ?? null),
                'pk' => $c->Key === 'PRI',
                'auto' => str_contains((string) $c->Extra, 'auto_increment'),
                'null' => $c->Null === 'YES',
                'default' => $c->Default,
            ];
        }

        return $this->meta = $cols;
    }

    private function pk(): string
    {
        if ($this->pkOverride) {
            return $this->pkOverride;
        }
        foreach ($this->meta() as $c) {
            if ($c->pk) {
                return $c->name;
            }
        }
        throw new RuntimeException('No primary key for '.$this->table);
    }

    /** crud_type per field (mirrors get_field_types / get_type). */
    private function fieldInfo(): array
    {
        $out = [];
        foreach ($this->meta() as $c) {
            $type = $this->typeOverrides[$c->name][0] ?? null;
            if (isset($this->uploads[$c->name])) {
                $type = 'upload_file'.($type === 'readonly' ? '_readonly' : '');
            } elseif ($type === null && isset($this->relations[$c->name])) {
                $type = 'relation';
            } elseif ($type !== null && isset($this->relations[$c->name])) {
                $type = 'relation_'.$type;
            }
            if ($type === null) {
                $type = match (true) {
                    in_array($c->db_type, ['int', 'mediumint', 'bigint', 'smallint']) => 'integer',
                    $c->db_type === 'tinyint' => ((int) $c->db_max_length === 1 ? 'true_false' : 'integer'),
                    in_array($c->db_type, ['varchar', 'char', 'decimal', 'float', 'double']) => 'string',
                    in_array($c->db_type, ['text', 'mediumtext', 'longtext', 'blob']) => 'text',
                    $c->db_type === 'date' => 'date',
                    in_array($c->db_type, ['datetime', 'timestamp']) => 'datetime',
                    default => 'string',
                };
            }
            $out[$c->name] = (object) [
                'name' => $c->name,
                'crud_type' => $type,
                'extras' => $this->uploads[$c->name] ?? ($this->typeOverrides[$c->name][1] ?? false),
                'db_type' => $c->db_type,
                'db_max_length' => $c->db_max_length,
                'default' => $c->default,
                'db_null' => $c->null,
                'display_as' => $this->displayAs[$c->name] ?? ucfirst(str_replace('_', ' ', $c->name)),
                'required' => in_array($c->name, $this->required, true),
            ];
        }
        foreach (array_unique(array_merge($this->fieldsAdd ?? [], $this->fieldsEdit ?? [])) as $name) {
            if (! isset($out[$name]) && ! isset($this->nn[$name])) {   // not a table column: create it for the form like Grocery_CRUD did
                $out[$name] = (object) [
                    'name' => $name, 'crud_type' => $this->typeOverrides[$name][0] ?? 'string', 'extras' => $this->typeOverrides[$name][1] ?? false,
                    'db_type' => null, 'db_max_length' => null, 'default' => null, 'db_null' => true,
                    'display_as' => $this->displayAs[$name] ?? ucfirst(str_replace('_', ' ', $name)),
                    'required' => in_array($name, $this->required, true), 'virtual' => true,
                ];
            }
        }
        foreach ($this->nn as $name => $nn) {
            $out[$name] = (object) [
                'name' => $name, 'crud_type' => 'relation_n_n', 'extras' => $nn, 'db_type' => null, 'db_max_length' => null,
                'default' => null,
                'display_as' => $this->displayAs[$name] ?? ucfirst(str_replace('_', ' ', $name)),
                'required' => in_array($name, $this->required, true),
            ];
        }

        return $out;
    }

    private function l(string $k): string
    {
        return self::$lang[$k] ?? $k;
    }

    // ------------------------------------------------------------------ urls

    private function url(string $suffix = ''): string
    {
        return url($this->basePath.'/'.$suffix);
    }

    // ------------------------------------------------------------------ queries

    private function alias(string $field, string $prefix): string
    {
        return $prefix.substr(md5($field), 0, 8);
    }

    private function baseQuery()
    {
        $q = DB::table($this->table);
        foreach ($this->where as $w) {
            [$col, $val] = $w;
            if (isset($w[2])) {   // via relation
                $rel = $this->relations[$col] ?? abort(500, "whereVia needs a relation on $col");
                $q->whereIn($this->table.'.'.$this->safeCol($col),
                    DB::table($rel['table'])->select($this->relatedPk($rel['table']))
                        ->where(preg_replace('/[^a-zA-Z0-9_]/', '', $w[2]), $val));

                continue;
            }
            if (($w[3] ?? '=') === 'IN') {
                $q->whereIn($this->table.'.'.$this->safeCol($col), $val);

                continue;
            }
            $q->where($this->table.'.'.$this->safeCol($col), $w[3] ?? '=', $val);
        }

        return $q;
    }

    private function safeCol(string $c): string
    {
        if (! isset($this->meta()[$c])) {
            abort(400);
        }

        return $c;
    }

    private function titleExpr(array $rel, string $alias): string
    {
        $title = $rel['title'];
        if (str_contains($title, '{')) {
            $expr = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', fn ($m) => "', COALESCE(`$alias`.`{$m[1]}`, ''), '", str_replace("'", "\\'", $title));

            return "CONCAT('".$expr."')";
        }

        return '`'.$alias.'`.`'.preg_replace('/[^a-zA-Z0-9_]/', '', $title).'`';
    }

    /** Query with relation joins applied (used by list, count, export). */
    private function joined()
    {
        $q = $this->baseQuery();
        $select = [$this->table.'.*'];
        foreach ($this->relations as $field => $rel) {
            $j = $this->alias($field, 'j');
            $relPk = $this->relatedPk($rel['table']);
            $q->leftJoin($rel['table'].' as '.$j, $j.'.'.$relPk, '=', $this->table.'.'.$field);
            $select[] = DB::raw($this->titleExpr($rel, $j).' as `'.$this->alias($field, 's').'`');
        }
        foreach ($this->nn as $name => $nn) {
            $selPk = $this->relatedPk($nn->selectionTable);
            $rt = $this->ident($nn->relationTable);
            $st = $this->ident($nn->selectionTable);
            $select[] = DB::raw('(SELECT GROUP_CONCAT(DISTINCT '.$this->nnTitle($nn).") FROM `{$st}` LEFT JOIN `{$rt}` ON `{$rt}`.`".$this->ident($nn->pkSel)."` = `{$st}`.`{$selPk}` WHERE `{$rt}`.`".$this->ident($nn->pkThis).'` = `'.$this->table.'`.`'.$this->pk()."` GROUP BY `{$rt}`.`".$this->ident($nn->pkThis)."`) AS `{$name}`");
        }
        $q->select($select);

        return $q;
    }

    private function ident(string $s): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $s);
    }

    /** SQL expression for the title of an n-n selection row (supports {a} {b} templates). */
    private function nnTitle(object $nn): string
    {
        $st = $this->ident($nn->selectionTable);
        if (str_contains($nn->title, '{')) {
            $e = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', fn ($m) => "', COALESCE(`{$st}`.`{$m[1]}`, ''), '", str_replace("'", "\'", $nn->title));

            return "CONCAT('".$e."')";
        }

        return "`{$st}`.`".$this->ident($nn->title).'`';
    }

    private function relatedPk(string $table): string
    {
        foreach (DB::select('SHOW COLUMNS FROM `'.str_replace('`', '', $table).'`') as $c) {
            if ($c->Key === 'PRI') {
                return $c->Field;
            }
        }
        throw new RuntimeException("No primary key on $table");
    }

    /** list columns as [field_name => display_as]; relation columns use their alias field name. */
    private function listColumns(): array
    {
        $info = $this->fieldInfo();
        $names = $this->columnList ?: array_values(array_map(fn ($c) => $c->name, array_filter($this->meta(), fn ($c) => ! $c->auto)));
        $cols = [];
        foreach ($names as $n) {
            // Columns that are not in the table are allowed (value comes from callbackColumn or stays empty), like Grocery_CRUD.
            $cols[] = (object) [
                'field_name' => isset($this->relations[$n]) ? $this->alias($n, 's') : $n,
                'source' => $n,
                'display_as' => $info[$n]->display_as ?? $this->displayAs[$n] ?? ucfirst(str_replace('_', ' ', $n)),
                'virtual' => ! isset($info[$n]),
            ];
        }

        return $cols;
    }

    /** Applies search from the request (ajax_list / ajax_list_info / export / print). */
    private function filtered()
    {
        $q = $this->joined();
        $data = $this->request->all();
        $cols = $this->listColumns();
        $bySource = [];
        foreach ($cols as $c) {
            $bySource[$c->field_name] = $c;
        }
        $like = function ($query, string $expr, string $text, string $bool = 'and') {
            $query->whereRaw($expr.' LIKE ?', ['%'.addcslashes($text, '%_\\').'%'], $bool);
        };
        $exprFor = function (object $c) {
            if ($c->virtual || isset($this->nn[$c->source])) {
                return null;   // handled separately (n-n via HAVING) or not searchable (virtual)
            }
            if (isset($this->relations[$c->source])) {
                $rel = $this->relations[$c->source];

                return $this->titleExpr($rel, $this->alias($c->source, 'j'));
            }

            return '`'.$this->table.'`.`'.$c->source.'`';
        };

        $text = $data['search_text'] ?? null;
        $fields = $data['search_field'] ?? null;
        if (is_array($fields) && is_array($text)) {
            foreach ($fields as $k => $name) {
                $name = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $name);
                $val = (string) ($text[$k] ?? '');
                if ($val !== '' && isset($bySource[$name])) {
                    $this->searchColumn($q, $bySource[$name], $exprFor, $val, $like);
                }
            }
        } elseif (is_string($text) && $text !== '') {
            if (is_string($fields) && $fields !== '') {
                $name = preg_replace('/[^a-zA-Z0-9_]/', '', $fields);
                if (isset($bySource[$name])) {
                    $this->searchColumn($q, $bySource[$name], $exprFor, $text, $like);
                }
            } else {   // search all visible columns (OR)
                $q->where(function ($w) use ($cols, $exprFor, $text) {
                    foreach ($cols as $c) {
                        if (($expr = $exprFor($c)) !== null) {
                            $w->orWhereRaw($expr.' LIKE ?', ['%'.addcslashes($text, '%_\\').'%']);
                        }
                    }
                });
            }
        }

        return $q;
    }

    private function searchColumn($q, object $c, Closure $exprFor, string $text, Closure $like): void
    {
        if (isset($this->nn[$c->source])) {
            $q->havingRaw('`'.$this->ident($c->source).'` LIKE ?', ['%'.addcslashes($text, '%_\\').'%']);
        } elseif (($expr = $exprFor($c)) !== null) {
            $like($q, $expr, $text);
        }
    }

    private function countQuery($q): int
    {
        // Drop "table.*" (it can clash with n-n aliases of the same name); keep the pk and computed aliases that HAVING may use.
        $q = clone $q;
        $q->columns = array_merge([$this->table.'.'.$this->pk()], array_values(array_filter((array) $q->columns, fn ($c) => ! is_string($c))));

        return (int) DB::table(DB::raw('('.$q->toSql().') as c'))->mergeBindings($q)->count();
    }

    private function applyOrderAndPaging($q, bool $page = true)
    {
        $cols = $this->listColumns();
        $allowed = [];
        foreach ($cols as $c) {
            $allowed[$c->field_name] = isset($this->relations[$c->source]) ? '`'.$c->field_name.'`' : '`'.$this->table.'`.`'.$c->source.'`';
        }
        $order = $this->request->input('order_by');
        if (is_array($order) && ! empty($order[0]) && isset($allowed[$order[0]])) {
            $q->orderByRaw($allowed[$order[0]].' '.(strtolower((string) ($order[1] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC'));
        } elseif ($this->orderBy) {
            $q->orderBy($this->table.'.'.$this->safeCol($this->orderBy[0]), $this->orderBy[1]);
        } else {
            $q->orderBy($this->table.'.'.$this->pk());
        }
        if ($page) {
            $per = (int) $this->request->input('per_page', 10);
            $per = in_array($per, [10, 25, 50, 100, 1000], true) ? $per : 10;
            $pg = max(1, (int) $this->request->input('page', 1));
            $q->limit($per)->offset(($pg - 1) * $per);
        }

        return $q;
    }

    /** rows prepared like change_list(): column values formatted, urls attached. */
    private function rows($rows): array
    {
        $info = $this->fieldInfo();
        $cols = $this->listColumns();
        $pk = $this->pk();
        $out = [];
        foreach ($rows as $r) {
            $row = (object) (array) $r;
            $formatted = (object) [];
            foreach ($cols as $c) {
                $val = $row->{$c->field_name} ?? null;
                if (isset($this->callbackColumn[$c->source])) {
                    $formatted->{$c->field_name} = ($this->callbackColumn[$c->source])($val, $row);
                } elseif (isset($this->relations[$c->source])) {
                    $formatted->{$c->field_name} = e($val);
                } elseif ($c->virtual) {
                    $formatted->{$c->field_name} = e($val);
                } elseif (isset($this->nn[$c->source])) {
                    $formatted->{$c->field_name} = e(str_replace(',', ', ', (string) $val));
                } else {
                    $formatted->{$c->field_name} = $this->listValue($info[$c->source], $val);
                }
            }
            $id = $row->{$pk};
            $formatted->primary_key_value = $id;
            $formatted->edit_url = $this->url('edit').'/'.$id;
            $formatted->delete_url = $this->url('delete').'/'.$id;
            $formatted->read_url = $this->url('read').'/'.$id;
            $formatted->clone_url = $this->url('clone').'/'.$id;
            $out[] = $formatted;
        }

        return $out;
    }

    private function listValue(object $f, $v)
    {
        switch ($f->crud_type) {
            case 'true_false':
                $text = is_array($f->extras) ? $f->extras : [0 => $this->l('form_inactive'), 1 => $this->l('form_active')];

                return array_key_exists((string) $v, $text) ? $text[(string) $v] : e($v);
            case 'integer':
            case 'hidden':
            case 'invisible':
                return e($v);
            case 'password':
                return '******';
            case 'text':
                return e(strip_tags((string) $v));
            case 'dropdown':
                return is_array($f->extras) && array_key_exists((string) $v, $f->extras) ? e($f->extras[(string) $v]) : e($v);
            case 'upload_file':
                if (empty($v)) {
                    return '';
                }
                $url = url($f->extras->path.'/'.rawurlencode((string) $v));
                if (preg_match('/\.(jpe?g|png|gif|tiff)$/i', (string) $v)) {
                    return '<a href="'.e($url).'" class="image-thumbnail"><img src="'.e($url).'" height="50px"></a>';
                }

                return '<a href="'.e($url).'" target="_blank">'.e($v).'</a>';
            case 'date':
                if (! empty($v) && $v !== '0000-00-00' && $v !== '1970-01-01') {
                    [$y, $m, $d] = array_pad(explode('-', $v), 3, 0);

                    return date('d/m/Y', mktime(0, 0, 0, (int) $m, (int) $d, (int) $y));
                }

                return '';
            case 'datetime':
                if (! empty($v) && $v !== '0000-00-00 00:00:00' && $v !== '1970-01-01 00:00:00') {
                    [$y, $m, $d] = array_pad(explode('-', substr($v, 0, 10)), 3, 0);
                    [$hh, $mm] = array_pad(explode(':', substr($v, 11)), 2, 0);

                    return date('d/m/Y - H:i', mktime((int) $hh, (int) $mm, 0, (int) $m, (int) $d, (int) $y));
                }

                return '';
            default:
                return e($v);
        }
    }

    // ------------------------------------------------------------------ assets

    private function css(string ...$files): void
    {
        foreach ($files as $f) {
            $this->css[sha1($f)] = url($f);
        }
    }

    private function js(string ...$files): void
    {
        foreach ($files as $f) {
            $this->js[sha1($f)] = url($f);
        }
    }

    private function jsVars(array $vars): void
    {
        $s = "<script type=\"text/javascript\">\n";
        foreach ($vars as $k => $v) {
            $s .= "\tvar $k = '$v';\n";
        }
        $this->views .= $s."\n</script>\n";
    }

    private function inlineJs(string $js): void
    {
        $this->views .= "<script type=\"text/javascript\">\n{$js}\n</script>\n";
    }

    private function finish(): array
    {
        $this->jsVars([
            'default_javascript_path' => url(self::ASSETS.'/js'),
            'default_css_path' => url(self::ASSETS.'/css'),
            'default_texteditor_path' => url(self::ASSETS.'/texteditor'),
            'default_theme_path' => url(self::ASSETS.'/themes'),
            'base_url' => url('/').'/',
        ]);

        return ['output' => $this->views, 'css_files' => $this->css, 'js_files' => $this->js];
    }

    private function formOpen(string $action, string $attrs): string
    {
        return '<form action="'.e($action).'" '.$attrs.' accept-charset="utf-8">'."\n".csrf_field();
    }

    // ------------------------------------------------------------------ list

    private function listData(?string $successPk = null): array
    {
        $q = $this->applyOrderAndPaging($this->joined(), true);
        $rows = $this->rows($q->get());
        $total = $this->countQuery($this->filtered());
        $success = null;
        if ($successPk !== null) {
            $success = $this->l('insert_success_message');
            if ($successPk !== '' && ! $this->unset['edit']) {
                $success .= " <a class='go-to-edit-form' href='".$this->url('edit/'.e($successPk))."'>".$this->l('form_edit').' '.e($this->subject).'</a> ';
            }
        }

        return [
            'crud' => $this, 'list' => $rows, 'columns' => $this->listColumns(), 'total' => $total,
            'subject' => $this->subject, 'subject_plural' => $this->subjectPlural,
            'unset' => $this->unset, 'success_message' => $success,
            'paging_options' => ['10', '25', '50', '100', '1000'], 'default_per_page' => 10,
            'actions' => [],
        ];
    }

    private function showList(?string $successPk)
    {
        $d = $this->listData($successPk);
        $this->css(self::THEME.'/css/bootstrap/bootstrap.min.css', self::THEME.'/css/elusive-icons/css/elusive-icons.min.css',
            self::THEME.'/css/common.css', self::THEME.'/css/list.css', self::THEME.'/css/general.css',
            self::THEME.'/css/plugins/animate.min.css', self::THEME.'/css/main.css');
        $this->js(self::ASSETS.'/js/'.self::JQUERY, self::THEME.'/build/js/global-libs.min.js',
            self::THEME.'/js/jquery-plugins/gc-dropdown.min.js', self::THEME.'/js/jquery-plugins/gc-modal.min.js',
            self::THEME.'/js/jquery-plugins/bootstrap-growl.min.js', self::THEME.'/js/jquery-plugins/jquery.print-this.js',
            self::THEME.'/js/datagrid/gcrud.datagrid.js', self::THEME.'/js/datagrid/list.js');

        $this->jsVars(['dialog_forms' => '']);
        $this->views .= view('crud.list', $d + [
            'add_url' => $this->url('add'), 'export_url' => $this->url('export'), 'print_url' => $this->url('print'),
            'ajax_list_url' => $this->url('ajax_list'), 'ajax_list_info_url' => $this->url('ajax_list_info'),
            'delete_multiple_url' => $this->url('delete_multiple'),
            'unique_hash' => md5($this->basePath), 'base_url' => url('/').'/', 'lang' => self::$lang,
        ])->render();

        return $this->finish();
    }

    private function ajaxList()
    {
        $q = $this->applyOrderAndPaging($this->filtered(), true);
        $rows = $this->rows($q->get());
        $html = view('crud.list_tbody', [
            'list' => $rows, 'columns' => $this->listColumns(), 'unset' => $this->unset, 'actions' => [], 'lang' => self::$lang,
        ])->render();

        return response()->json([
            'current_total_results' => $this->countQuery($this->filtered()),
            'tbody_html' => $html,
        ]);
    }

    // ------------------------------------------------------------------ forms

    /** Fields shown on forms (everything except auto-increment PK); invisible/hidden handled like the library. */
    private function formFields(string $mode = 'add'): array
    {
        $explicit = in_array($mode, ['edit', 'read'], true) ? $this->fieldsEdit : $this->fieldsAdd;
        if ($explicit !== null) {
            return array_values($explicit);
        }
        $names = array_values(array_filter(array_keys($this->meta()), fn ($n) => ! $this->meta()[$n]->auto));
        foreach (array_keys($this->nn) as $n) {
            if (! in_array($n, $names, true)) {
                $names[] = $n;
            }
        }

        return $names;
    }

    private function input(object $f, $value): string
    {
        $n = $f->name;
        $v = $value === null ? '' : (is_scalar($value) ? (string) $value : '');
        switch ($f->crud_type) {
            case 'integer':
                $this->js(self::ASSETS.'/js/jquery_plugins/jquery.numeric.min.js', self::ASSETS.'/js/jquery_plugins/config/jquery.numeric.config.js');
                $max = $f->db_max_length ? "maxlength='{$f->db_max_length}'" : '';

                return "<input id='field-{$n}' name='{$n}' type='text' value='".e($v)."' class='numeric form-control' $max />";
            case 'true_false':
                $isNull = $value === null || $value === '';
                $sv = $isNull ? null : (string) $value;
                $t = is_array($f->extras) && isset($f->extras[1]) ? $f->extras[1] : $this->l('form_active');
                $fl = is_array($f->extras) && isset($f->extras[0]) ? $f->extras[0] : $this->l('form_inactive');
                $c1 = ($sv === '1' || ($isNull && (string) $f->default === '1')) ? "checked = 'checked'" : '';
                $c0 = ($sv === '0' || ($isNull && (string) $f->default === '0')) ? "checked = 'checked'" : '';

                return "<div class='pretty-radio-buttons'>"
                    ."<div class=\"radio\"><label>\n<input id='field-{$n}-true' type=\"radio\" name=\"{$n}\" value=\"1\" $c1 />\n$t\n</label> </div>"
                    ."<div class=\"radio\"><label>\n<input id='field-{$n}-false' type=\"radio\" name=\"{$n}\" value=\"0\" $c0 />\n$fl\n</label> </div></div>";
            case 'string':
                $max = '';
                if ($f->db_max_length) {
                    $len = $f->db_max_length;
                    if (in_array($f->db_type, ['decimal', 'float'], true)) {
                        $len = ((int) explode(',', (string) $len)[0]) + 1;
                    }
                    $max = "maxlength='{$len}'";
                }

                return "<input id='field-{$n}' class='form-control' name='{$n}' type='text' value=\"".e($v)."\" $max />";
            case 'hidden':
                return "<input id='field-{$n}' type='hidden' name='{$n}' value='".e($f->extras ?: $v)."' />";
            case 'readonly':
                if (is_array($value)) {
                    $v = implode(', ', $value);
                }

                return '<div id="field-'.$n.'" class="readonly_label">'.($v !== '' ? e($v) : '&nbsp;').'</div>';
            case 'dropdown':
                $this->css(self::ASSETS.'/css/jquery_plugins/chosen/chosen.css');
                $this->js(self::ASSETS.'/js/jquery_plugins/jquery.chosen.min.js', self::ASSETS.'/js/jquery_plugins/config/jquery.chosen.config.js');
                $title = str_replace('{field_display_as}', $f->display_as, $this->l('set_relation_title'));
                $s = "<select id='field-{$n}' name='{$n}' class='chosen-select' data-placeholder='".e($title)."'>";
                foreach (['' => ''] + (array) $f->extras as $ov => $ol) {
                    $sel = $v !== '' && (string) $v === (string) $ov ? "selected='selected'" : '';
                    $s .= "<option value='".e($ov)."' $sel >".e($ol).'</option>';
                }

                return $s.'</select>';
            case 'date':
                $this->css(self::ASSETS.'/css/ui/simple/jquery-ui-1.10.1.custom.min.css');
                $this->js(self::ASSETS.'/js/jquery_plugins/ui/jquery-ui-1.10.3.custom.min.js', self::ASSETS.'/js/jquery_plugins/config/jquery.datepicker.config.js');
                $d = '';
                if ($v !== '' && $v !== '0000-00-00' && $v !== '1970-01-01') {
                    [$y, $m, $dd] = array_pad(explode('-', substr($v, 0, 10)), 3, 0);
                    $d = date('d/m/Y', mktime(0, 0, 0, (int) $m, (int) $dd, (int) $y));
                }

                return "<input id='field-{$n}' name='{$n}' type='text' value='".e($d)."' maxlength='10' class='datepicker-input form-control' />
		<a class='datepicker-input-clear' tabindex='-1'>".$this->l('form_button_clear').'</a> (dd/mm/yyyy)';
            case 'datetime':
                $this->css(self::ASSETS.'/css/ui/simple/jquery-ui-1.10.1.custom.min.css', self::ASSETS.'/css/jquery_plugins/jquery.ui.datetime.css', self::ASSETS.'/css/jquery_plugins/jquery-ui-timepicker-addon.css');
                $this->js(self::ASSETS.'/js/jquery_plugins/ui/jquery-ui-1.10.3.custom.min.js', self::ASSETS.'/js/jquery_plugins/jquery-ui-timepicker-addon.js', self::ASSETS.'/js/jquery_plugins/config/jquery-ui-timepicker-addon.config.js');
                $d = '';
                if ($v !== '' && $v !== '0000-00-00 00:00:00' && $v !== '1970-01-01 00:00:00') {
                    [$y, $m, $dd] = array_pad(explode('-', substr($v, 0, 10)), 3, 0);
                    $d = date('d/m/Y', mktime(0, 0, 0, (int) $m, (int) $dd, (int) $y)).substr($v, 10);
                }

                return "<input id='field-{$n}' name='{$n}' type='text' value='".e($d)."' maxlength='19' class='datetime-input form-control' />
		<a class='datetime-input-clear' tabindex='-1'>".$this->l('form_button_clear').'</a>
		(dd/mm/yyyy) hh:mm:ss';
            case 'text':
                if (in_array($n, $this->noEditor, true)) {
                    return "<textarea id='field-{$n}' name='{$n}' class='form-control'>".e($v).'</textarea>';
                }
                $this->js(self::ASSETS.'/texteditor/ckeditor/ckeditor.js', self::ASSETS.'/texteditor/ckeditor/adapters/jquery.js', self::ASSETS.'/js/jquery_plugins/config/jquery.ckeditor.config.js');

                return "<textarea id='field-{$n}' name='{$n}' class='texteditor' >".e($v).'</textarea>';
            case 'upload_file':
            case 'upload_file_readonly':
                return $this->uploadInput($f, $v, $f->crud_type === 'upload_file_readonly');
            case 'relation_n_n':
                return $this->nnInput($f, is_array($value) ? $value : []);
            case 'relation':
                $this->css(self::ASSETS.'/css/jquery_plugins/chosen/chosen.css');
                $this->js(self::ASSETS.'/js/jquery_plugins/jquery.chosen.min.js', self::ASSETS.'/js/jquery_plugins/config/jquery.chosen.config.js');
                $this->inlineJs("var ajax_relation_url = '".$this->url('ajax_relation')."';\n");
                $title = str_replace('{field_display_as}', $f->display_as, $this->l('set_relation_title'));
                $s = "<select id='field-{$n}'  name='{$n}' class='chosen-select' data-placeholder='".e($title)."' style='width:100%'><option value=''></option>";
                foreach ($this->relationOptions($this->relations[$n]) as $id => $label) {
                    $sel = $v !== '' && (string) $v === (string) $id ? "selected='selected'" : '';
                    $s .= "<option value='".e($id)."' $sel >".e($label).'</option>';
                }

                return $s.'</select>';
            default:
                throw new RuntimeException("Crud: field type '{$f->crud_type}' ({$n}) is not implemented yet");
        }
    }

    private function uploadInput(object $f, string $value, bool $readonly): string
    {
        $up = $f->extras;
        $url = $value !== '' ? url($up->path.'/'.rawurlencode($value)) : '';
        $isImage = (bool) preg_match('/\.(jpe?g|png|gif|tiff)$/i', $value);
        if ($readonly) {
            return $value === '' ? '<div id="field-'.$f->name.'" class="readonly_label">&nbsp;</div>'
                : '<a href="'.e($url).'" target="_blank">'.($isImage ? '<img src="'.e($url).'" height="50px">' : e($value)).'</a>';
        }

        $this->css(self::ASSETS.'/css/ui/simple/jquery-ui-1.10.1.custom.min.css', self::ASSETS.'/css/jquery_plugins/file_upload/file-uploader.css', self::ASSETS.'/css/jquery_plugins/file_upload/jquery.fileupload-ui.css');
        $this->js(self::ASSETS.'/js/jquery_plugins/ui/jquery-ui-1.10.3.custom.min.js', self::ASSETS.'/js/jquery_plugins/tmpl.min.js', self::ASSETS.'/js/jquery_plugins/load-image.min.js',
            self::ASSETS.'/js/jquery_plugins/jquery.iframe-transport.js', self::ASSETS.'/js/jquery_plugins/jquery.fileupload.js', self::ASSETS.'/js/jquery_plugins/config/jquery.fileupload.config.js');
        $this->css(self::ASSETS.'/css/jquery_plugins/fancybox/jquery.fancybox.css');
        $this->js(self::ASSETS.'/js/jquery_plugins/jquery.fancybox-1.3.4.js', self::ASSETS.'/js/jquery_plugins/jquery.easing-1.3.pack.js', self::ASSETS.'/js/jquery_plugins/config/jquery.fancybox.config.js');

        $unique = random_int(100000000, 2147483647);
        $types = implode('|', $up->allowed);
        $ui = '.'.implode(',.', $up->allowed);
        $mb = number_format($up->max / 1048576, 0).'MB';
        $this->inlineJs('
			var upload_info_'.$unique.' = {
				accepted_file_types: /(\\.|\\/)('.$types.')$/i,
				accepted_file_types_ui : "'.$ui.'",
				max_file_size: '.$up->max.',
				max_file_size_ui: "'.$mb.'"
			};
			var string_upload_file 	= "'.$this->l('form_upload_a_file').'";
			var string_delete_file 	= "'.$this->l('string_delete_file').'";
			var string_progress 			= "'.$this->l('string_progress').'";
			var error_on_uploading 			= "'.$this->l('error_on_uploading').'";
			var message_prompt_delete_file 	= "'.$this->l('message_prompt_delete_file').'";
			var error_max_number_of_files 	= "'.$this->l('error_max_number_of_files').'";
			var error_accept_file_types 	= "'.$this->l('error_accept_file_types').'";
			var error_max_file_size 		= "'.str_replace('{max_file_size}', $mb, $this->l('error_max_file_size')).'";
			var error_min_file_size 		= "'.$this->l('error_min_file_size').'";
			var base_url = "'.url('/').'/";
			var upload_a_file_string = "'.$this->l('form_upload_a_file').'";
		');

        $hideUploader = $value !== '' ? 'display:none;' : '';
        $hideFile = $value === '' ? 'display:none;' : '';
        $inputName = $this->alias($f->name, 's');
        $s = '<span class="fileinput-button qq-upload-button" id="upload-button-'.$unique.'" style="'.$hideUploader.'">
			<span>'.$this->l('form_upload_a_file').'</span>
			<input type="file" name="'.$inputName.'" class="gc-file-upload" rel="'.e($this->url('upload_file/'.$f->name)).'" id="'.$unique.'">
			<input class="hidden-upload-input" type="hidden" name="'.e($f->name).'" value="'.e($value).'" rel="'.$inputName.'" />
		</span>';
        $this->css(self::ASSETS.'/css/jquery_plugins/file_upload/fileuploader.css');
        $s .= "<div id='uploader_$unique' rel='$unique' class='grocery-crud-uploader' style='$hideUploader'></div>";
        $s .= "<div id='success_$unique' class='upload-success-url' style='$hideFile padding-top:7px;'>";
        $s .= "<a href='".e($url)."' id='file_$unique' class='open-file";
        $s .= $isImage ? " image-thumbnail'><img src='".e($url)."' height='50px'>" : "' target='_blank'>".e($value);
        $s .= '</a> ';
        $s .= "<a href='javascript:void(0)' id='delete_$unique' class='delete-anchor'>".$this->l('form_upload_delete').'</a> ';
        $s .= "</div><div style='clear:both'></div>";
        $s .= "<div id='loading-$unique' style='display:none'><span id='upload-state-message-$unique'></span> <span class='qq-upload-spinner'></span> <span id='progress-$unique'></span></div>";
        $s .= "<div style='display:none'><a href='".e($this->url('upload_file/'.$f->name))."' id='url_$unique'></a></div>";
        $s .= "<div style='display:none'><a href='".e($this->url('delete_file/'.$f->name))."' id='delete_url_$unique' rel='".e($value)."' ></a></div>";

        return $s;
    }

    private const DENIED_EXT = ['php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar', 'pht', 'phps', 'exe', 'bat', 'cmd', 'sh', 'cgi', 'pl', 'asp', 'aspx', 'jsp', 'html', 'htm', 'js', 'svg', 'htaccess'];

    private function uploadFile(string $field)
    {
        $up = $this->uploads[$field] ?? null;
        abort_unless($up, 404);
        $file = $this->request->file($this->alias($field, 's'));
        $fail = fn (string $m) => response()->json(['success' => false, 'message' => $m]);

        if (! $file || ! $file->isValid()) {
            return $fail($this->l('error_on_uploading'));
        }
        if ($file->getSize() > $up->max) {
            return $fail(str_replace('{max_file_size}', number_format($up->max / 1048576, 0).'MB', $this->l('error_max_file_size')));
        }
        if ($file->getSize() < 1) {
            return $fail($this->l('error_min_file_size'));
        }

        $clientExt = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        $imageOnly = ! array_diff($up->allowed, ['gif', 'jpeg', 'jpg', 'png', 'tiff']);
        if ($imageOnly) {
            $info = @getimagesize($file->getPathname());
            $ext = match ($info[2] ?? 0) {
                IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', default => null,
            };
            $accepted = $ext !== null && (in_array($ext, $up->allowed, true) || ($ext === 'jpg' && in_array('jpeg', $up->allowed, true)));
            if (! $accepted) {
                return $fail($this->l('error_accept_file_types'));
            }
        } else {
            $ext = $clientExt;
            if (! in_array($ext, $up->allowed, true) || in_array($ext, self::DENIED_EXT, true)) {
                return $fail($this->l('error_accept_file_types'));
            }
        }

        $name = bin2hex(random_bytes(8)).'.'.$ext;
        if ($imageOnly) {
            if (($info[0] ?? 0) > 4000 || ($info[1] ?? 0) > 4000) {
                return $fail($this->l('error_accept_file_types'));
            }
            $img = @imagecreatefromstring((string) file_get_contents($file->getPathname()));
            if (! $img) {
                return $fail($this->l('error_accept_file_types'));
            }
            $target = public_path($up->path.'/'.$name);
            imagealphablending($img, false);
            imagesavealpha($img, true);
            $saved = match ($ext) {
                'png' => imagepng($img, $target), 'gif' => imagegif($img, $target), default => imagejpeg($img, $target, 90)
            };
            imagedestroy($img);
            if (! $saved) {
                return $fail($this->l('error_on_uploading'));
            }
        } else {
            $file->move(public_path($up->path), $name);
        }
        $this->request->session()->push('crud_uploads', $name);

        return response()->json(['success' => true, 'files' => [[
            'name' => $name, 'size' => filesize(public_path($up->path.'/'.$name)), 'type' => 'image/'.($ext === 'jpg' ? 'jpeg' : $ext),
            'url' => url($up->path.'/'.$name),
        ]]]);
    }

    private function deleteFile(string $field, string $name)
    {
        $up = $this->uploads[$field] ?? null;
        $ok = false;
        if ($up && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name)) {
            $mine = in_array($name, (array) $this->request->session()->get('crud_uploads', []), true);
            $referenced = $this->baseQuery()->where($this->table.'.'.$this->safeCol($field), $name)->exists();
            if ($mine || $referenced) {
                $path = public_path($up->path.'/'.$name);
                if (! is_file($path) || @unlink($path)) {
                    // ids first: MySQL cannot UPDATE a table while selecting from it in a subquery
                    $ids = $this->baseQuery()->where($this->table.'.'.$field, $name)->pluck($this->table.'.'.$this->pk())->all();
                    if ($ids) {
                        DB::table($this->table)->whereIn($this->pk(), $ids)->update([$field => '']);
                    }
                    $ok = true;
                }
            }
        }

        return response()->json(['success' => $ok]);
    }

    /** [id => title] of rows currently linked to $pk. */
    private function nnSelected(object $nn, $pk): array
    {
        $selPk = $this->relatedPk($nn->selectionTable);
        $rt = $this->ident($nn->relationTable);
        $st = $this->ident($nn->selectionTable);
        $rows = DB::table($rt)->join($st, "{$rt}.".$this->ident($nn->pkSel), '=', "{$st}.{$selPk}")
            ->where("{$rt}.".$this->ident($nn->pkThis), $pk)
            ->selectRaw("{$rt}.".$this->ident($nn->pkSel).' as id, '.$this->nnTitle($nn).' as title')
            ->orderByRaw($this->nnTitle($nn))->get();
        $out = [];
        foreach ($rows as $r) {
            $out[$r->id] = $r->title;
        }

        return $out;
    }

    /** [id => title] of every selectable row (restricted by the screen's where). */
    private function nnAllowed(object $nn): array
    {
        $selPk = $this->relatedPk($nn->selectionTable);
        $st = $this->ident($nn->selectionTable);
        $q = DB::table($st)->selectRaw("`{$st}`.`{$selPk}` as id, ".$this->nnTitle($nn).' as title');
        if ($nn->where) {
            ($nn->where)($q);
        }
        $q->orderByRaw($this->nnTitle($nn));
        $out = [];
        foreach ($q->get() as $r) {
            $out[$r->id] = $r->title;
        }

        return $out;
    }

    private function nnInput(object $f, array $selected): string
    {
        $nn = $f->extras;
        $this->css(self::ASSETS.'/css/jquery_plugins/chosen/chosen.css');
        $this->js(self::ASSETS.'/js/jquery_plugins/jquery.chosen.min.js', self::ASSETS.'/js/jquery_plugins/config/jquery.chosen.config.js');
        $this->inlineJs("var ajax_relation_url = '".$this->url('ajax_relation')."';\n");
        $unselected = array_diff_key($this->nnAllowed($nn), $selected);
        if (! $unselected && ! $selected) {
            return 'Please add '.e($f->display_as).' first';
        }
        $title = str_replace('{field_display_as}', $f->display_as, $this->l('set_relation_title'));
        $s = "<select id='field-{$f->name}' name='{$f->name}[]' multiple='multiple' size='8' class='chosen-multiple-select' data-placeholder='".e($title)."' style='width:510px;' >";
        foreach ($unselected as $id => $name) {
            $s .= "<option value='".e($id)."'>".e($name).'</option>';
        }
        foreach ($selected as $id => $name) {
            $s .= "<option value='".e($id)."' selected='selected'>".e($name).'</option>';
        }

        return $s.'</select>';
    }

    /** Replace the links of one n-n field for row $pk with $ids, keeping only ids the screen allows. */
    private function nnSync(object $nn, $pk, $ids): void
    {
        $ids = array_values(array_unique(array_filter((array) $ids, fn ($i) => is_scalar($i) && $i !== '')));
        $ids = array_values(array_intersect($ids, array_map('strval', array_keys($this->nnAllowed($nn)))));
        $rt = $this->ident($nn->relationTable);
        $pkThis = $this->ident($nn->pkThis);
        $pkSel = $this->ident($nn->pkSel);
        $del = DB::table($rt)->where($pkThis, $pk);
        if ($ids) {
            $del->whereNotIn($pkSel, $ids);
        }
        $del->delete();
        foreach ($ids as $id) {
            if (! DB::table($rt)->where($pkThis, $pk)->where($pkSel, $id)->exists()) {
                DB::table($rt)->insert([$pkThis => $pk, $pkSel => $id]);
            }
        }
    }

    private function relationOptions(array $rel): array
    {
        $pk = $this->relatedPk($rel['table']);
        $alias = 'r';
        $q = DB::table($rel['table'].' as r')->select(['r.'.$pk.' as id', DB::raw($this->titleExpr($rel, $alias).' as title')]);
        if ($rel['where'] instanceof Closure) {
            ($rel['where'])($q);
        } else {
            foreach ($rel['where'] as $col => $val) {
                $q->where('r.'.preg_replace('/[^a-zA-Z0-9_]/', '', $col), $val);
            }
        }
        $q->orderByRaw($rel['orderBy'] ? '`r`.`'.preg_replace('/[^a-zA-Z0-9_]/', '', $rel['orderBy']).'`' : $this->titleExpr($rel, $alias));
        $out = [];
        foreach ($q->get() as $r) {
            $out[$r->id] = $r->title;
        }

        return $out;
    }

    private function findOrNull(string $pk)
    {
        return $this->baseQuery()->where($this->table.'.'.$this->pk(), $pk)->first();
    }

    private function findRow(string $pk)
    {
        $row = $this->baseQuery()->where($this->table.'.'.$this->pk(), $pk)->first();
        abort_unless($row, 404);

        return $row;
    }

    private function showForm(string $mode, ?string $pk = null)
    {
        $info = $this->fieldInfo();
        $values = null;
        if (in_array($mode, ['edit', 'read', 'clone'], true)) {
            abort_if($pk === null, 404);
            $values = $this->findRow($pk);
        }

        $this->js(self::ASSETS.'/js/'.self::JQUERY);
        $fields = [];
        $hidden = [];
        foreach ($this->formFields($mode) as $name) {
            $f = $info[$name];
            if ($mode === 'read') {
                if (($this->typeOverrides[$name][0] ?? null) === 'hidden') {
                    continue;
                }
                $f = clone $f;
                $f->crud_type = 'readonly';
            }
            if ($f->crud_type === 'invisible') {
                continue;
            }
            $val = $values->{$name} ?? null;
            if (isset($this->nn[$name])) {
                $val = $pk !== null ? $this->nnSelected($this->nn[$name], $pk) : [];
            }
            $cb = $mode === 'edit' ? ($this->callbackEditField[$name] ?? null) : ($mode === 'add' ? ($this->callbackAddField[$name] ?? null) : null);
            $html = $cb ? $cb($val, $mode === 'add' ? null : $pk, $f, $values) : $this->input($f, $val);
            if ($f->crud_type === 'hidden') {
                $hidden[] = $html;

                continue;
            }
            $fields[] = (object) ['name' => $name, 'display_as' => $f->display_as, 'required' => $f->required, 'input' => $html];
        }

        $this->css(self::THEME.'/css/bootstrap/bootstrap.min.css', self::THEME.'/css/elusive-icons/css/elusive-icons.min.css',
            self::THEME.'/css/common.css', self::THEME.'/css/general.css', self::THEME.'/css/add-edit-form.css', self::THEME.'/css/main.css');
        $this->js(self::THEME.'/build/js/global-libs.min.js');
        if ($mode === 'add' || $mode === 'clone') {
            $this->js(self::THEME.'/js/form/add.min.js');
        } elseif ($mode === 'edit') {
            $this->js(self::THEME.'/js/form/edit.min.js');
        }

        $this->views .= view('crud.form', [
            'mode' => $mode, 'fields' => $fields, 'hidden' => $hidden, 'subject' => $this->subject, 'lang' => self::$lang,
            'unique_hash' => md5($this->basePath), 'unset_back_to_list' => $this->unset['back_to_list'],
            'insert_url' => $this->url('insert'), 'update_url' => $this->url('update/'.$pk),
            'validation_url' => $mode === 'edit' ? $this->url('update_validation/'.$pk) : $this->url('insert_validation'),
            'list_url' => $this->url(''),
            'csrf' => csrf_field(),
        ])->render();
        $this->inlineJs("var js_date_format = 'dd/mm/yy';");

        return $this->finish();
    }

    // ------------------------------------------------------------------ writes

    private function validation(?string $pk = null): JsonResponse
    {
        [$ok, $errors] = $this->validate($pk);

        if ($ok) {
            return response()->json(['success' => true]);
        }
        $msg = '';
        foreach ($errors as $e) {
            $msg .= '<p>'.e($e)."</p>\n";
        }

        return response()->json(['success' => false, 'error_message' => $msg, 'error_fields' => $errors]);
    }

    private function validate(?string $pk = null): array
    {
        $info = $this->fieldInfo();
        $errors = [];
        $required = array_unique(array_merge($this->required, array_keys(array_filter($this->rules, fn ($r) => in_array('required', $r[1], true)))));
        foreach ($required as $f) {
            if (! isset($info[$f]) || in_array($info[$f]->crud_type, ['invisible'], true)) {
                continue;
            }
            $v = $this->request->input($f);
            if (is_string($v) && in_array('trim', $this->rules[$f][1] ?? [], true)) {
                $v = trim($v);
            }
            if ($v === null || (is_string($v) && trim($v) === '') || $v === []) {
                $errors[$f] = 'The '.($this->rules[$f][0] ?? $info[$f]->display_as).' field is required.';
            }
        }
        foreach ($this->uniqueFields as $f) {
            $v = $this->request->input($f);
            if (! isset($errors[$f]) && is_string($v) && isset($info[$f])) {
                $v = in_array('trim', $this->rules[$f][1] ?? [], true) ? trim($v) : $v;
                $q = DB::table($this->table)->where($f, $v);
                if ($pk !== null) {
                    $q->where($this->pk(), '!=', $pk);
                }
                if ($q->exists()) {
                    $errors[$f] = 'The '.($this->rules[$f][0] ?? $info[$f]->display_as).' field must contain a unique value.';
                }
            }
        }

        // A relation field (a drop-down of related records) may only carry one of the options the user is offered; the form's
        // where() limits those to the user's own records, so a crafted POST cannot point a record at an id the user cannot see.
        // On edit the value already stored on the record stays acceptable (it may belong to an option that was deactivated since).
        $posted = $this->postedFields($pk === null ? 'add' : 'edit');
        foreach ($posted as $f => $v) {
            if (! isset($this->relations[$f]) || isset($errors[$f]) || $v === null || $v === '') {
                continue;
            }
            $options = array_map('strval', array_keys($this->relationOptions($this->relations[$f])));
            $current = $pk !== null ? DB::table($this->table)->where($this->pk(), $pk)->value($f) : null;
            if (! is_scalar($v) || (! in_array((string) $v, $options, true) && ($current === null || (string) $v !== (string) $current))) {
                $errors[$f] = 'The '.($this->rules[$f][0] ?? ($info[$f]->display_as ?? $f)).' field has an invalid value.';
            }
        }

        // Numeric columns must receive numbers: MySQL in non-strict mode would store 'abc' as 0 and silently cut '12abc' to 12.
        foreach ($posted as $f => $v) {
            $i = $info[$f] ?? null;
            if ($i === null || isset($errors[$f]) || $v === null || $v === '' || ! in_array($i->crud_type, ['integer', 'string'], true)) {
                continue;
            }
            $isInt = in_array($i->db_type, ['int', 'mediumint', 'bigint', 'smallint', 'tinyint'], true);
            $isDec = in_array($i->db_type, ['decimal', 'float', 'double'], true);
            if (! $isInt && ! $isDec) {
                continue;
            }
            $s = is_scalar($v) ? trim((string) $v) : '';
            $valid = $isInt ? (bool) preg_match('/^-?\d{1,18}$/', $s) : (bool) preg_match('/^-?\d{1,15}(\.\d{1,6})?$/', $s);
            if (! $valid) {
                $errors[$f] = 'The '.($this->rules[$f][0] ?? $i->display_as).' field must be a number.';
            } elseif (in_array($f, $this->nonNegative, true) && str_starts_with($s, '-') && (float) $s != 0.0) {
                $errors[$f] = 'The '.($this->rules[$f][0] ?? $i->display_as).' field must not be negative.';
            }
        }

        return [empty($errors), $errors];
    }

    /** Only fields that are on the form (not invisible) may come from the browser. */
    private function postedFields(string $mode = 'add'): array
    {
        $info = $this->fieldInfo();
        $post = [];
        foreach ($this->formFields($mode) as $name) {
            if (in_array($info[$name]->crud_type, ['invisible', 'readonly'], true)) {
                continue;
            }
            if ($info[$name]->crud_type === 'hidden') {
                // Hidden fields are decided by the server: a fixed value (e.g. lcoCode) or nothing; never taken from the browser.
                if ($info[$name]->extras) {
                    $post[$name] = $info[$name]->extras;
                }

                continue;
            }
            if ($this->request->has($name)) {
                $post[$name] = $this->request->input($name);
            }
        }

        return $post;
    }

    /** dd/mm/yyyy (UK format used by the UI) -> SQL; empty on nullable columns -> NULL (as Grocery_CRUD did). */
    private function convertDates(array $data): array
    {
        $info = $this->fieldInfo();
        foreach ($data as $k => $v) {
            $t = $info[$k]->crud_type ?? null;
            if (! is_string($v) || ! in_array($t, ['date', 'datetime'], true)) {
                continue;
            }
            if ($v === '' && $info[$k]->db_null) {
                $data[$k] = null;

                continue;
            }
            $day = $this->sqlDate(substr($v, 0, 10));
            $data[$k] = $t === 'date' ? $day : $day.substr($v, 10);
        }

        return $data;
    }

    /** Upload columns only accept a plain file name (what upload_file returned), never a path. */
    private function cleanUploads(array $data): array
    {
        foreach (array_keys($this->uploads) as $field) {
            if (array_key_exists($field, $data) && ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', (string) $data[$field])) {
                $data[$field] = '';
            }
        }

        return $data;
    }

    private function sqlDate(string $d): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || $d === '') {
            return $d;
        }
        $p = preg_split('/[-.\/ ]/', $d);
        if (count($p) < 3) {
            return $d;
        }

        return date('Y-m-d', mktime(0, 0, 0, (int) $p[1], (int) $p[0], (int) $p[2]));
    }

    private function insert(): \Symfony\Component\HttpFoundation\Response
    {
        [$ok] = $this->validate();
        if (! $ok) {
            return response()->json(['success' => false]);
        }
        $post = $this->postedFields('add');
        if ($this->beforeInsert) {
            $r = ($this->beforeInsert)($post);
            if ($r === false) {
                return response()->json(['success' => false]);
            }
            if ($r instanceof \Symfony\Component\HttpFoundation\Response) {
                return $r;   // the callback handled the request itself
            }
            if (is_array($r) && $r) {
                $post = $r;
            }
        }
        $data = array_diff_key(array_intersect_key($post, $this->meta()), $this->nn);
        $data = $this->cleanUploads($this->convertDates(array_filter($data, fn ($k) => ! $this->meta()[$k]->auto, ARRAY_FILTER_USE_KEY)));
        $after = true;
        $id = DB::transaction(function () use ($data, $post, &$after) {
            $id = DB::table($this->table)->insertGetId($data);
            foreach ($this->nn as $name => $nn) {
                $this->nnSync($nn, $id, $post[$name] ?? []);
            }
            if ($this->afterInsert) {
                $res = ($this->afterInsert)($post, $id);
                if ($res === false) {
                    $after = false;
                } elseif ($res instanceof \Symfony\Component\HttpFoundation\Response) {
                    $after = $res;
                }
            }

            return $id;
        });
        if ($after instanceof \Symfony\Component\HttpFoundation\Response) {
            return $after;
        }
        if (! $after) {
            return response()->json(['success' => false]);
        }

        $msg = '<p>'.$this->l('insert_success_message');
        if (! $this->unset['back_to_list'] && ! empty($id) && ! $this->unset['edit']) {
            $msg .= " <a class='go-to-edit-form' href='".$this->url('edit/'.$id)."'>".$this->l('form_edit').' '.e($this->subject).'</a> ';
            if ($this->request->input('is_ajax') !== 'true') {
                $msg .= $this->l('form_or');
            }
        }
        if (! $this->unset['back_to_list'] && $this->request->input('is_ajax') !== 'true') {
            $msg .= " <a href='".$this->url('')."'>".$this->l('form_go_back_to_list').'</a>';
        }
        $msg .= '</p>';

        return response()->json([
            'success' => true, 'insert_primary_key' => $id, 'success_message' => $msg,
            'success_list_url' => $this->url('success/'.$id),
        ]);
    }

    private function update(?string $pk): JsonResponse
    {
        abort_if($pk === null, 404);
        $this->findRow($pk);   // 404 if not in the user's scope
        [$ok] = $this->validate($pk);
        if (! $ok) {
            return response()->json(['success' => false]);
        }
        $post = $this->postedFields('edit');
        if ($this->beforeUpdate) {
            $r = ($this->beforeUpdate)($post, $pk);
            if ($r === false) {
                return response()->json(['success' => false]);
            }
            if (is_array($r) && $r) {
                $post = $r;
            }
        }
        $data = array_diff_key(array_intersect_key($post, $this->meta()), $this->nn);
        unset($data[$this->pk()]);
        $data = $this->cleanUploads($this->convertDates($data));
        DB::transaction(function () use ($data, $post, $pk) {
            if ($data) {
                $this->baseQuery()->where($this->table.'.'.$this->pk(), $pk)->update($data);
            }
            foreach ($this->nn as $name => $nn) {
                $this->nnSync($nn, $pk, $post[$name] ?? []);
            }
        });

        $msg = '<p>'.$this->l('update_success_message');
        if (! $this->unset['back_to_list'] && $this->request->input('is_ajax') !== 'true') {
            $msg .= " <a href='".$this->url('')."'>".$this->l('form_go_back_to_list').'</a>';
        }

        return response()->json([
            'success' => true, 'insert_primary_key' => true, 'success_message' => $msg.'</p>',
            'success_list_url' => $this->url('success/'.$pk),
        ]);
    }

    private function deleteResponse(array $ids): JsonResponse
    {
        $ok = count($ids) > 0;
        foreach ($ids as $id) {
            if (! is_scalar($id) || ! $this->findOrNull((string) $id)) {
                $ok = false;

                continue;
            }
            DB::transaction(function () use ($id, &$ok) {
                foreach ($this->nn as $nn) {
                    DB::table($this->ident($nn->relationTable))->where($this->ident($nn->pkThis), $id)->delete();
                }
                if ($this->baseQuery()->where($this->table.'.'.$this->pk(), $id)->limit(1)->delete() !== 1) {
                    $ok = false;
                }
            });
        }
        if (! $ok) {
            return response()->json(['success' => false, 'error_message' => '<p>'.$this->l('delete_error_message').'</p>']);
        }

        return response()->json(['success' => true, 'success_message' => '<p>'.$this->l('delete_success_message').'</p>']);
    }

    // ------------------------------------------------------------------ export / print

    private function exportRows(): array
    {
        $q = $this->applyOrderAndPaging($this->filtered(), false);

        return $this->rows($q->get());
    }

    private function export()
    {
        $cols = $this->listColumns();
        $s = '';
        foreach ($cols as $c) {
            $s .= $c->display_as."\t";
        }
        $s .= "\n";
        foreach ($this->exportRows() as $row) {
            foreach ($cols as $c) {
                $s .= str_replace(["\t", "\r", "\n"], ' ', html_entity_decode(strip_tags((string) $row->{$c->field_name})))."\t";
            }
            $s .= "\n";
        }
        $s = "\xFF\xFE".mb_convert_encoding($s, 'UTF-16LE', 'UTF-8');

        return response($s, 200, [
            'Content-type' => 'application/vnd.ms-excel;charset=UTF-16LE',
            'Content-Disposition' => 'attachment; filename="export-'.date('Y-m-d_H:i:s').'.xls"',
        ]);
    }

    private function printPage()
    {
        $cols = $this->listColumns();
        $s = "<meta charset=\"utf-8\" /><style type=\"text/css\" >\n#print-table{ color: #000; background: #fff; font-family: Verdana,Tahoma,Helvetica,sans-serif; font-size: 13px;}\n#print-table table tr td, #print-table table tr th{ border: 1px solid black; border-bottom: none; border-right: none; padding: 4px 8px 4px 4px}\n#print-table table{ border-bottom: 1px solid black; border-right: 1px solid black}\n#print-table table tr th{text-align: left;background: #ddd}\n#print-table table tr:nth-child(odd){background: #eee}\n</style>";
        $s .= "<div id='print-table'><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" ><tr>";
        foreach ($cols as $c) {
            $s .= '<th>'.e($c->display_as).'</th>';
        }
        $s .= '</tr>';
        foreach ($this->exportRows() as $row) {
            $s .= '<tr>';
            foreach ($cols as $c) {
                $s .= '<td>'.strip_tags((string) $row->{$c->field_name}).'</td>';
            }
            $s .= '</tr>';
        }

        return response($s.'</table></div>');
    }
}

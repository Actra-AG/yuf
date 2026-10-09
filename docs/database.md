# Database

## Connection

`FrameworkDb` is a PDO connection that throws on every error, uses native prepared statements and returns native types.
It holds no static state: one object is one connection, which the project creates once and passes on.

```php
$db = new FrameworkDb(
    connectionParameters: DbConnectionParameters::forMysql(
        dbSettings: new DbSettings(
            hostName: 'db.example.com',
            databaseName: 'app',
            userName: 'app_user',
            password: $password,
        ),
    ),
);
```

- `DbSettings` validates its values (they end up in the DSN and the init command). `sqlSafeUpdates` is on by default
  (MySQL refuses `UPDATE` and `DELETE` without a key).
- Use `?` placeholders for every value (`select()`, `selectRows()`, `selectRow()`, `execute()`, `prepareSelect()`). The
  values are `float|int|string|null`: convert booleans to `0` / `1`.
- `createInQuery()` creates the placeholders of an `IN (...)` list, `getLastInsertId()` returns the generated ID as
  `int`, `getQueryLog()` the queries run with `logQuery: true`.
- A `DbRuntimeException` carries the SQL and the number of bound values, never the values (they may be personal data).
- Tests: `new FrameworkDb(connectionParameters: new DbConnectionParameters(dsn: 'sqlite::memory:'))`.

## Typed rows

`select()` returns untyped `stdClass` rows. `selectRows()` and `selectRow()` return `DbRow` objects whose getters throw
a `DbRowValueException` (column, expected and actual type) for a missing column, `NULL` in a non-nullable getter or a
wrong type. Nothing is cast silently.

```php
final readonly class User
{
    public function __construct(
        public int $id,
        public string $name,
        public ?DateTimeImmutable $lastLogin,
        public UserStatusEnum $status,
    ) {
    }

    public static function fromRow(DbRow $row): User
    {
        return new User(
            id: $row->getInt(column: 'id'),
            name: $row->getString(column: 'name'),
            lastLogin: $row->getNullableDateTimeImmutable(column: 'last_login'),
            status: $row->getEnum(column: 'status', enumClass: UserStatusEnum::class),
        );
    }
}

$row = $db->selectRow(sql: 'SELECT * FROM users WHERE id = ?', parameters: [$id]);
$user = $row === null ? null : User::fromRow(row: $row);
$users = array_map(callback: User::fromRow(...), array: $db->selectRows(sql: 'SELECT * FROM users ORDER BY name'));
```

- Getters: `getString`, `getInt`, `getFloat`, `getDecimal` (canonical decimal string like `'12.50'`), `getBool`
  (`0`/`1`), `getDateTimeImmutable` (DATE, DATETIME, TIMESTAMP), `getEnum`, each with a `getNullable…` variant (except
  `getBool`), and `has()`.
- `selectRow()` returns `null` for no row and throws `DbRowCountException` for more than one.
- `DbSelectStmt` has the same (`executeAndFetchRows()`, `executeAndFetchRow()`).
- Date and time columns are parsed in the PHP default time zone: the time zone of the database session must match.

### Typed values in table columns

Columns of a `DbResultTable` or `SmartTable` get each row as `TableItem`. `getRow()` returns it as `DbRow`;
`renderValue()` returns the HTML-encoded value, `getRawValue()` the untyped one.

```php
$dbResultTable->addColumn(abstractTableColumn: new CallbackColumn(
    identifier: 'path',
    label: 'Pfad',
    callbackFunction: fn(TableItem $tableItem): string => HtmlEncoder::encode(
        value: Category::getPath(id: $tableItem->getRow()->getInt(column: 'ID')),
    ),
));
```

## Query builder

`DbQuery` is created from an SQL query and extended before execution:

```php
$query = DbQuery::createFromSqlQuery(
    query: 'SELECT users.* FROM users WHERE users.active = ?',
    parameters: [1],
);
$query->addJoinPart(joinPart: 'LEFT JOIN groups ON groups.id = users.group_id', parameters: []);
$query->addWherePart(wherePart: 'groups.name = ?', parameters: ['admin']);
$query->addOrderPart(column: 'users.name');
```

- The query of `createFromSqlQuery()` consists of `SELECT`, `FROM`, optional joins and an optional `WHERE` only.
  `GROUP BY`, `HAVING`, `ORDER BY`, `LIMIT` and `UNION` are rejected: sorting and paging are added by `DbQuery`
  (`addOrderPart()`, the offset and row count of `selectFromDb()`).
- Every added part has exactly one parameter per `?`. Joins go between `FROM` and `WHERE`; each `addWherePart()`
  condition is wrapped in parentheses and combined with `AND`.
- `addOrderPart()` without `parameters` accepts column names only (letters, digits, `_`, `.`, backticks; several
  separated by a comma). Never pass user input that was not checked against your own whitelist of sortable columns.
- With `parameters`, the first argument of `addOrderPart()` is an SQL expression, taken over unchanged: it must never
  contain user input and must not end with `ASC` or `DESC` (use `ascending:`). `clearOrderParts()` removes all sorting.

```php
$query->addWherePart(
    wherePart: 'MATCH(products.searchContent) AGAINST (? IN BOOLEAN MODE)',
    parameters: [$searchTerm],
);
$query->addOrderPart(
    column: 'MATCH(products.searchContent) AGAINST (? IN BOOLEAN MODE)',
    parameters: [$searchTerm],
    ascending: false,
);
```

## Boolean search

`SearchQueryBuilder::createBooleanQuery()` turns a search text into a `WHERE` condition with bound parameters:

```php
$data = SearchQueryBuilder::createBooleanQuery(
    spaceSeparatedFieldNames: 'person.firstName person.lastName',
    queryText: $searchTerm, // e.g. 'haas +kap -"old address"'
);
$query->addWherePart(wherePart: $data->query, parameters: $data->params);
```

- Every word must be contained in at least one of the fields (`LIKE '%word%'`). Words are combined with `OR`; `and`,
  `or`, `not` or `+word` / `-word` change that. `"quoted phrases"` are one word.
- Case-insensitive, HTML tags are removed, `%`, `_`, `?` and `\` are searched literally. An empty text gives `1=1`.
- The field names are validated (columns, optionally `table.column` or quoted with backticks); an invalid one throws an
  `InvalidArgumentException`.

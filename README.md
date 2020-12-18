# Buto-Plugin-WfMysql
Handle MySQL queries.

## SQL
Example of query to use in execute method.

```
account_email:
  sql: |
    SELECT email from account where id=?;
  select:
    - email
  params:
    -
      type: s
      value: get:id
```

## Event

At the end of method execute.

```
wf_mysql_execute_after
```
```
wfEvent::run('wf_mysql_execute_after', array('sql_script' => $this->getSqlScript()));
```

### Turn off event
One can turn this off by set param event to false.
```
$mysql =new PluginWfMysql();
$mysql->event = false;
```


## Metods

### execute
Execute sql. One could add params to replace data. The "get:" prefix will also be replaced by wfReguest params.

```
array('get' => array('id' => '1234'))
```

### getOne
Get one record as PluginWfArray object. Add optional sql data to fill result with empty params.

```
$rs = $plugin_wf_mysql->getOne(array('sql' => $sql->get()));
```
### getMany
Get records i array.

```
$rs = $plugin_wf_mysql->getMany();
```

## Replace
Replace string.

### User ID
[user_id] will be replaced by param session user_id.

### Remote address
[remote_addr] from server variable.

### Any session param
[SESSION:user_id]


## LIKE
To find "Alice" in table account with LIKE search set param like to true.
```
sql: select name from account where name LIKE ?;
select:
  - name
params:
  -
    type: s
    value: lic
    like: true 
```

## Get sql from file

On could use method getSqlFromFile to get sql and also replace items.

```
$mysql =new PluginWfMysql();
$sql = $mysql->getSqlFromFile('account', '/plugin/_some_/_plugin_/mysql/sql.yml');
```
Example.
```
account:
  sql: select id, email from account
  select:
    - id
    - email
```

### Replace in sql
One could replace like this.
```
account:
  sql: select id, [replace.test] from account
  select:
    - id
    - email
replace:
  test: email
```

### Replace from session
One could replace from session.
```
account:
  sql: select id, email from account where id='[SESSION:user_id]'
  select:
    - id
    - email
```

### Replace from get params
One could replace from get param.
```
account:
  sql: select id, email from account where id=?
  select:
    - id
    - email
  params:
    id:
      type: s
      value: get:id
```

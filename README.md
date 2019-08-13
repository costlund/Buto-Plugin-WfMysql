# Buto-Plugin-WfMysql
Handle MySQL queries.

## SQL
Example of query.

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



## Metods

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



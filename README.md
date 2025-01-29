# Buto-Plugin-WfMysql

<p>Handle MySQL queries. Set up in yml file and then use the execute method. Ur simply use runSql method with just sql script. </p>

<a name="key_0"></a>

## Settings



<a name="key_0_0"></a>

### SQL

<p>Example of query to use in execute method.</p>
<pre><code>account_email:
  sql: |
    SELECT email from account where id=?;
  select:
    - email
  params:
    -
      type: s
      value: get:id</code></pre>

<a name="key_0_0_0"></a>

#### LIKE

<p>To find "Alice" in table account with LIKE search set param like to true.</p>
<pre><code>sql: select name from account where name LIKE ?;
select:
  - name
params:
  -
    type: s
    value: lic
    like: true </code></pre>

<a name="key_0_0_1"></a>

#### Get sql from file

<p>On could use method getSqlFromFile to get sql and also replace items.</p>
<pre><code>$mysql =new PluginWfMysql();
$sql = $mysql-&gt;getSqlFromFile('account', '/plugin/_some_/_plugin_/mysql/sql.yml');</code></pre>
<p>Example.</p>
<pre><code>account:
  sql: select id, email from account
  select:
    - id
    - email</code></pre>

<a name="key_1"></a>

## Events



<a name="key_1_0"></a>

### wf_mysql_execute_after

<p>In method execute an event is fired with current sql script. In this example we use plugin mysql/log to log queries.</p>
<pre><code>events:
  wf_mysql_execute_after:
    -
      plugin: 'mysql/log'
      method: log</code></pre>
<p>This is what data is passing in method wfEvent:run.</p>
<pre><code>wfEvent::run('wf_mysql_execute_after', array('sql_script' =&gt; $this-&gt;getSqlScript()));</code></pre>
<p>One can turn this off by set param event to false.</p>
<pre><code>$mysql =new PluginWfMysql();
$mysql-&gt;event = false;</code></pre>

<a name="key_2"></a>

## Methods



<a name="key_2_0"></a>

### conn

<p>Connection.</p>
<pre><code>server: '_ip_or_domain_'
database: '_name_of_db_'
user_name: '_user_name_'
password: '_pw_'</code></pre>
<p>Set PHP time zone (optional).</p>
<pre><code>set_php_time_zone: true</code></pre>
<p>One could crypt values if theme file /config/crypt.yml exist.
Read more how to crypt in readme for plugin crypt/openssl.</p>
<pre><code>password: 'crypt:_my_crypted_pw_'</code></pre>

<a name="key_2_1"></a>

### execute

<p>Execute sql. One could add params to replace data. The "get:" prefix will also be replaced by wfReguest params.</p>
<pre><code>array('get' =&gt; array('id' =&gt; '1234'))</code></pre>

<a name="key_2_2"></a>

### getOne

<p>Get one record as PluginWfArray object. Add optional sql data to fill result with empty params.</p>
<pre><code>$rs = $plugin_wf_mysql-&gt;getOne(array('sql' =&gt; $sql-&gt;get()));</code></pre>

<a name="key_2_3"></a>

### getMany

<p>Get records i array.</p>
<pre><code>$rs = $plugin_wf_mysql-&gt;getMany();</code></pre>

<a name="key_3"></a>

## Replace

<p>Replace string.</p>

<a name="key_3_0"></a>

### Replace in sql

<p>One could replace like this.</p>
<pre><code>account:
  sql: select id, [replace.test] from account
  select:
    - id
    - email
replace:
  test: email</code></pre>

<a name="key_3_1"></a>

### Replace param

<pre><code>account:
  sql: select id, email from account where [email]
  replace:
    email: "email='me@world.com'"
  select:
    - id
    - email</code></pre>

<a name="key_3_2"></a>

### user_id

<p>[user_id] will be replaced by param session user_id.</p>

<a name="key_3_3"></a>

### remote_addr

<p>[remote_addr] from server variable.</p>

<a name="key_3_4"></a>

### Session

<p>Any session param.</p>
<pre><code>[SESSION:user_id]</code></pre>
<pre><code>account:
  sql: select id, email from account where id='[SESSION:user_id]'
  select:
    - id
    - email</code></pre>
<p>Query session param with null value.</p>
<pre><code>account:
  sql: select id, email from account where [SESSION_EQUAL:_some_/_param_/disabled:account.disabled]
  select:
    - id
    - email</code></pre>

<a name="key_4"></a>

## Known issues

<p>When to load sql from file and the file is parsed by server this could happen.</p>
<pre><code>Parse error: Unmatched ')' in ../sql.yml on line 888</code></pre>
<p>This statement could cause this.</p>
<pre><code>sql: select number from my_table where number&lt;?</code></pre>


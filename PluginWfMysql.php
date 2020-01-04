<?php
/**
<p>
Handle MySql.
</p>
<p>
Character 	Description
i 	corresponding variable has type integer
d 	corresponding variable has type double
s 	corresponding variable has type string
b 	corresponding variable is a blob and will be sent in packets</p>
 */
class PluginWfMysql{
  private $db_handler = null;
  private $stmt = null;
  private $data = array();
  public $settings = array('empty_strings_sets_to_null' => true);
  public $event = true;
  /**
  <p>
  A widget to test your MySql database.
  </p>
  */
  public static function widget_test($data){
    $mysql = new PluginWfMysql();
    $mysql->open(wfArray::get($data, 'data'));
    $result = $mysql->runSql(wfArray::get($data, 'data/sql'));
    wfHelp::yml_dump($data);
    wfHelp::yml_dump($result);
  }
  /**
   * Get db object.
   */
  public function open($conn) {
    if(is_null($this->db_handler)){
      $conn = wfSettings::getSettingsFromYmlString($conn);
      $db_handle = new mysqli(
              wfCrypt::decryptFromString($conn['server']), 
              wfCrypt::decryptFromString($conn['user_name']), 
              wfCrypt::decryptFromString($conn['password']), 
              wfCrypt::decryptFromString($conn['database'])
              );
      if ($db_handle->connect_error) {
        throw new Exception("PluginWfMysql could not connect to database.");
      }
      $this->db_handler = $db_handle;
      $this->execute(array('sql' => "SET CHARACTER SET utf8"));
    }
    return true;
  }
  /**
   * Run a query and return result in an array.
   * @param string $sql
   * @param string $key_field
   * @return array
   */
  public function runSql($sql, $key_field = 'id'){
    $array = array();
    $num_rows = null;
    $result = $this->db_handler->query($sql);
    if($result){
      if (isset($result->num_rows) && $result->num_rows > 0) {
        $num_rows = $result->num_rows;
        while($row = $result->fetch_assoc()) {
          if($key_field && isset($row[$key_field])){
            $array[$row[$key_field]] = $row;
          }else{
            $array[] = $row;
          }
        }
      } else {
        $num_rows = 0;
      }
    }else{
      if(!wfUser::hasRole('webmaster')){
        $sql = 'empty sql string because role issue';
      }
      die("PluginWfMysql failed: ". $this->db_handler->error.' ('.$sql.')');
    }
    return array('num_rows' => $num_rows, 'data' => $array);
  }
  /**
   * 
   * @param array $data
   * @param array $params, array('rs' => array('name' => 'James'))
   * @return boolean
   * Example of array:
    $data = array(
      'sql' => 'INSERT INTO people (id, pid, first_name, last_name, user_id) VALUES (?, ?, ?, ?, ?);',
      'params' => array(
        array('type' => 's', 'value' => wfCrypt::getUid()),
        array('type' => 's', 'value' => '20030303-3322'),
        array('type' => 's', 'value' => "molly'=1"),
        array('type' => 's', 'value' => 'ostlund'),
        array('type' => 's', 'value' => $user->get('user_id'))
        )
    );
   */
  public function execute($data, $params = array()){
    /**
     * 
     */
    if(!isset($data['sql'])){return false;}
    /**
     * Replace params type.
     */
    if(isset($data['params'])){
      foreach ($data['params'] as $key => $value) {
        if(strstr($data['params'][$key]['type'], 'varchar')){
          $data['params'][$key]['type'] = 's';
        }elseif(strstr($data['params'][$key]['type'], 'text')){
          $data['params'][$key]['type'] = 's';
        }elseif(strstr($data['params'][$key]['type'], 'date')){
          $data['params'][$key]['type'] = 's';
        }elseif(strstr($data['params'][$key]['type'], 'enum')){
          $data['params'][$key]['type'] = 's';
        }elseif(strstr($data['params'][$key]['type'], 'timestamp')){
          $data['params'][$key]['type'] = 's';
        }elseif(strstr($data['params'][$key]['type'], 'int')){
          $data['params'][$key]['type'] = 'i';
        }elseif(strstr($data['params'][$key]['type'], 'double')){
          $data['params'][$key]['type'] = 'd';
        }
      }
    }
    /**
     * Replace data in $data from $params.
     */
    if(sizeof($params)){
      $temp = new PluginWfArray($data);
      foreach ($params as $key => $value) {
        $temp->setByTag($value, $key);
      }
      $data = $temp->get();
    }
    /**
     * Replace [user_id].
     */
    if(strstr($data['sql'], '[user_id]')){
      $user = wfUser::getSession();
      $data['sql'] = str_replace('[user_id]', $user->get('user_id'), $data['sql']);
    }
    /**
     * Replace all session variables.
     */
    if(strstr($data['sql'], '[SESSION:')){
      wfPlugin::includeonce('wf/arraysearch');
      $search = new PluginWfArraysearch(true);
      $session = wfUser::getSession();
      $search->data = array('data' => $session->get());
      foreach ($search->get() as $key => $value) {
        $data['sql'] = str_replace('[SESSION:'.substr($value, 1).']', $session->get(substr($value, 1)), $data['sql']);
      }
    }
    /**
     * Set get parameters.
     * Example when post id:
      params:
        -
          type: s
          value: get:id
     */
    if(isset($data['params'])){
      foreach ($data['params'] as $key => $value) {
        if(substr($value['value'], 0, 4) == 'get:'){
          $data['params'][$key]['value'] = wfRequest::get(substr($value['value'], 4));
        }
      }
    }
    /**
     * If param type is i and value is on we set to 1.
     * For usage when using a checkbox.
     */
    if(isset($data['params'])){
      foreach ($data['params'] as $key => $value) {
        if($value['type']=='i' && $value['value']=='on'){
          $data['params'][$key]['value'] = 1;
        }
      }
    }
    /**
     * Replace [remote_addr].
     */
    if(strstr($data['sql'], '[remote_addr]')){
      $server = new PluginWfArray($_SERVER);
      $data['sql'] = str_replace('[remote_addr]', $server->get('REMOTE_ADDR'), $data['sql']);
    }
    /**
     * 
     */
    $stmt = $this->db_handler->prepare($data['sql']);
    if($stmt===false){
      throw new Exception("PluginWfMysql says: ".$this->db_handler->error." (---".$data['sql']."---)!");
    }
    if(isset($data['params']) && sizeof($data['params'])){
      /**
       * All empty strings sets to NULL.
       */
      if($this->settings['empty_strings_sets_to_null']){
        foreach ($data['params'] as $key => $value) {
          if($value['value'] === ''){
            $data['params'][$key]['value'] = null;
          }
        }
      }
      /**
       * 
       */
      $types = '';
      foreach ($data['params'] as $key => $value) {
        if(!isset($value['type']) || !isset($value['value']) && $value['value'] != null ){ exit("Error in params key $key for PluginWfMysql."); }
        $types .= $value['type'];
      }
      $eval = '$stmt->bind_param("'.$types.'"';
      foreach ($data['params'] as $key => $value) {
        $eval .= ', $data["params"]["'.$key.'"]["value"]';
      }
      $eval .= ');';
      eval($eval);
    }
    $bool = $stmt->execute();
    if(!$bool){
      throw new Exception("PluginWfMysql says: ".$this->db_handler->error." (---".$data['sql']."---)!");
    }
    $this->stmt = $stmt;
    $this->data = $data;
    if($this->event){
      wfEvent::run('wf_mysql_execute_after', array('sql_script' => $this->getSqlScript()));
    }
    return $bool;
  }
  /**
   * Get last insert id.
   */
  public function getLastInsertId(){
    $record = $this->runSql("SELECT LAST_INSERT_ID() AS id;", null);
    if($record['num_rows']==1){
      return $record['data'][0]['id'];
    }else{
      return null;
    }
  }
  /**
   * Get sql script where params are parsed.
   * @return string
   */
  public function getSqlScript(){
    wfPlugin::includeonce('wf/array');
    $data = new PluginWfArray($this->data);
    $sql = $data->get('sql');
    $params = $data->get('params');
    if($params){
      foreach ($params as $key => $value) {
        if($value['type']=='s'){
          $sql = $this->str_replace_first('?', "'".$value['value']."'", $sql);
        }else{
          if($value['value']===''){
            $value['value'] = 'NULL';
          }
          $sql = $this->str_replace_first('?', $value['value'], $sql);
        }
      }
    }
    return $sql;
  }
  /**
   * Replace first find character.
   */
  private function str_replace_first($search, $replace, $subject) {
    $pos = strpos($subject, $search);
    if ($pos !== false) {
      return substr_replace($subject, $replace, $pos, strlen($search));
    }
    return $subject;
  }  
  /**
   * 
   * @return type
   */
  public function getStmtAsArray(){
    $stmt = $this->stmt;
    $data = $this->data;
    if(isset($data['select'])){
      /**
       * Bind result to variables.
       */
      $eval = '$stmt->bind_result(';
      foreach ($data['select'] as $key => $value) {
        $eval .= '$result["'.$value.'"],';
      }
      $eval = substr($eval, 0, strlen($eval)-1);
      $eval .= ');';
      eval($eval);
      /**
       * Fetch into array.
       */
      $array = array();
      while($row = $stmt->fetch()) {
        $temp = array();
        foreach ($result as $key => $value) {
          $temp[$key] = $value;
        }
        $array[] = $temp;
      }
      return $array;
    }else{
      return array();
    }
  }
  public function getStmtAsArrayOne(){
    $stmt = $this->getStmtAsArray();
    if(sizeof($stmt)>0){
      return $stmt[0];
    }else{
      return null;
    }
  }
  /**
   * 
   * @return type
   */
  public function getStmt(){
    return $this->stmt;
  }
  public function transaction_start(){
    $this->db_handler->begin_transaction();
  }
  public function transaction_end(){
    $this->db_handler->commit();
  }
  public function getMany(){
    return $this->getStmtAsArray();
  }
  public function getOne($data = array()){
    $data = new PluginWfArray($data);
    $rs = $this->getStmtAsArrayOne();
    if(is_null($rs) &&  $data->get('sql')){
      $sql = new PluginWfArray($data->get('sql'));
      $temp = array();
      foreach ($sql->get('select') as $key => $value) {
        $temp[$value] = null;
      }
      $rs = $temp;
    }
    return new PluginWfArray($rs);
  }
}
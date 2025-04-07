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
  public $conn = null;
  public $affected_rows = null;
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
      $this->conn = $conn;
      $conn = wfSettings::getSettingsFromYmlString($conn);
      $conn = new PluginWfArray($conn);
      wfPlugin::includeonce('crypt/openssl');
      $crypt = new PluginCryptOpenssl();
      $db_handle = new mysqli(
        $crypt->decrypt_from_key($conn->get('server')), 
        $crypt->decrypt_from_key($conn->get('user_name')), 
        $crypt->decrypt_from_key($conn->get('password')), 
        $crypt->decrypt_from_key($conn->get('database'))
              );
      if ($db_handle->connect_error) {
        throw new Exception(__CLASS__."says: Could not connect to server ".$conn->get('server').".");
      }
      $this->db_handler = $db_handle;
      $this->execute(array('sql' => "SET CHARACTER SET utf8"));
      /**
       * time zone
       */
      if($conn->get('set_php_time_zone')){
        $this->execute(array('sql' => "SET time_zone='".$this->get_time_zone()."'"));
      }
    }
    return true;
  }
  private function get_time_zone(){
    $now = new DateTime();
    $mins = $now->getOffset() / 60;
    $sgn = ($mins < 0 ? -1 : 1);
    $mins = abs($mins);
    $hrs = floor($mins / 60);
    $mins -= $hrs * 60;
    return sprintf('%+d:%02d', $hrs*$sgn, $mins);
  }
  /**
   * Run a query and return result in an array.
   * @param string $sql
   * @param string $key_field (set to false to never render keys)
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
        throw new Exception("PluginWfMysql failed: ". $this->db_handler->error.'.');
      }else{
        throw new Exception("PluginWfMysql failed: ". $this->db_handler->error.' ('.$sql.').');
      }
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
     * replace
     */
    if(isset($data['replace'])){
      foreach($data['replace'] as $k => $v){
        $data['sql'] = wfPhpfunc::str_replace('['.$k.']', $v, $data['sql']);
      }
    }
    /**
     * 
     */
    if(!isset($data['sql'])){return false;}
    /**
     * Replace params type.
     */
    if(isset($data['params'])){
      foreach ($data['params'] as $key => $value) {
        if(wfPhpfunc::strstr($data['params'][$key]['type'], 'varchar')){
          $data['params'][$key]['type'] = 's';
        }elseif(wfPhpfunc::strstr($data['params'][$key]['type'], 'text')){
          $data['params'][$key]['type'] = 's';
        }elseif(wfPhpfunc::strstr($data['params'][$key]['type'], 'date')){
          $data['params'][$key]['type'] = 's';
        }elseif(wfPhpfunc::strstr($data['params'][$key]['type'], 'enum')){
          $data['params'][$key]['type'] = 's';
        }elseif(wfPhpfunc::strstr($data['params'][$key]['type'], 'timestamp')){
          $data['params'][$key]['type'] = 's';
        }elseif(wfPhpfunc::strstr($data['params'][$key]['type'], 'int')){
          $data['params'][$key]['type'] = 'i';
        }elseif(wfPhpfunc::strstr($data['params'][$key]['type'], 'double')){
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
    if(wfPhpfunc::strstr($data['sql'], '[user_id]')){
      $user = wfUser::getSession();
      $data['sql'] = wfPhpfunc::str_replace('[user_id]', $user->get('user_id'), $data['sql']);
    }
    /**
     * Replace all session variables.
     */
    if(wfPhpfunc::strstr($data['sql'], '[SESSION:')){
      wfPlugin::includeonce('wf/arraysearch');
      $search = new PluginWfArraysearch(true);
      $session = wfUser::getSession();
      $search->data = array('data' => $session->get());
      foreach ($search->get() as $key => $value) {
        $data['sql'] = wfPhpfunc::str_replace('[SESSION:'.wfPhpfunc::substr($value, 1).']', $session->get(wfPhpfunc::substr($value, 1)), $data['sql']);
      }
    }
    /**
     * SESSION_EQUAL
     */
    if(wfPhpfunc::strstr($data['sql'], '[SESSION_EQUAL:')){
      /**
       * Get prepare data.
       */
      $temp = new PluginWfArray();
      $pos = 0;
      $session = wfUser::getSession();
      for($i = 0; $i<substr_count($data['sql'], '[SESSION_EQUAL:'); $i++){
        $pos = strpos($data['sql'], '[SESSION_EQUAL:', $pos+1);
        $pos_to = strpos($data['sql'], ']', $pos+1);
        $length = $pos_to-$pos;
        $temp->set("$i/pos", $pos);
        $temp->set("$i/pos_to", $pos_to);
        $temp->set("$i/length", $length);
        $text = wfPhpfunc::substr($data['sql'], $pos, $length+1);
        $temp->set("$i/text", $text);
        wfPlugin::includeonce('string/array');
        $plugin = new PluginStringArray();
        $text = wfPhpfunc::str_replace('[', '', $text);
        $text = wfPhpfunc::str_replace(']', '', $text);
        $temp->set("$i/data", $plugin->from_char($text, ':'));
        $temp->set("$i/value", $session->get($temp->get("$i/data/1")));
        if(is_null($session->get($temp->get("$i/data/1")))){
          $temp->set("$i/sql", "isnull(".$temp->get("$i/data/2").")");
        }else{
          $temp->set("$i/sql", $temp->get("$i/data/2")."=".$temp->get("$i/value"));
        }
      }
      /**
       * Replace
       */
      foreach($temp->get() as $k => $v){
        $data['sql'] = wfPhpfunc::str_replace($temp->get("$k/text"), $temp->get("$k/sql"), $data['sql']);
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
        if(wfPhpfunc::substr($value['value'], 0, 4) == 'get:'){
          $data['params'][$key]['value'] = wfRequest::get(wfPhpfunc::substr($value['value'], 4));
        }
      }
    }
    /**
     * like
     */
    if(isset($data['params'])){
      foreach ($data['params'] as $key => $value) {
        if(isset($data['params'][$key]['like'])){
          $data['params'][$key]['value'] = '%'.$data['params'][$key]['value'].'%';
        }
      }
    }
    /**
     * If param type is i and value is on (and not 0) we set it to 1.
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
    if(wfPhpfunc::strstr($data['sql'], '[remote_addr]')){
      $server = new PluginWfArray($_SERVER);
      $data['sql'] = wfPhpfunc::str_replace('[remote_addr]', $server->get('REMOTE_ADDR'), $data['sql']);
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
    /**
     * 
     */
    $this->affected_rows = mysqli_affected_rows($this->db_handler);
    /**
     * 
     */
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
      return substr_replace($subject, (string) $replace, $pos, wfPhpfunc::strlen($search));
    }
    return $subject;
  }  
  /**
   * 
   * @return array
   */
  public function getStmtAsArray(){
    $stmt = $this->stmt;
    $data = $this->data;
    $result = array();
    if(isset($data['select'])){
      /**
       * Bind result to variables.
       */
      $eval = '$stmt->bind_result(';
      foreach ($data['select'] as $key => $value) {
        $eval .= '$result["'.$value.'"],';
      }
      $eval = wfPhpfunc::substr($eval, 0, wfPhpfunc::strlen($eval)-1);
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
  public function getMany($data = array('keys' => array())){
    /**
     * 
     */
    $data = new PluginWfArray($data);
    /**
     * 
     */
    $rs =  $this->getStmtAsArray();
    /**
     * Make the array associative
     */
    if($data->get('keys')){
      $temp = array();
      foreach ($rs as $value) {
        $a_key = null;
        foreach ($data->get('keys') as $value2) {
          $a_key .= '_'.$value[$value2];
        }
        $a_key = wfPhpfunc::substr($a_key, 1);
        $temp[$a_key] = $value;
      }
      $rs = $temp;
    }
    /**
     * 
     */
    return $rs;
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
  public function get_sever_version(){
    return mysqli_get_server_version($this->db_handler);
  }
  public function getSqlFromFile($key, $file){
    /**
     * 
     */
    $sql = new PluginWfYml(wfGlobals::getAppDir().$file, $key);
    /**
     * 
     */
    if(wfPhpfunc::strstr($sql->get('sql'), '[replace.')){
      $replace = new PluginWfYml(wfGlobals::getAppDir().$file, 'replace');
      foreach ($replace->get() as $key => $value) {
        $sql->set('sql', wfPhpfunc::str_replace("[replace.$key]", $value, $sql->get('sql')));
      }
    }
    /**
     * 
     */
    return $sql;
  }
}

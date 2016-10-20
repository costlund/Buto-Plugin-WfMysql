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
    $db_handle = new mysqli($conn['server'], $conn['user_name'], $conn['password'], $conn['database']);
    if ($db_handle->connect_error) {
        die("PluginWfMysql failed: " . $conn->connect_error);
    }
    $this->db_handler = $db_handle;
    $this->execute(array('sql' => "SET CHARACTER SET utf8"));
    return true;
  }
  /**
   * Run a query and return result in an array.
   * @param type $sql
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
      die("PluginWfMysql failed: ". $this->db_handler->error);
    }
    return array('num_rows' => $num_rows, 'data' => $array);
  }
  /**
   * 
   * @param type $data
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
  public function execute($data){
    $this->data = $data;
    if(!isset($data['sql'])){return false;}
    $stmt = $this->db_handler->prepare($data['sql']);
    if(isset($data['params'])){
      $types = '';
      foreach ($data['params'] as $key => $value) {
        if(!isset($value['type']) || !isset($value['value'])){ return false; }
        $types .= $value['type'];
      }
      $eval = '$stmt->bind_param("'.$types.'"';
      foreach ($data['params'] as $key => $value) {
        $eval .= ', $data["params"]["'.$key.'"]["value"]';
      }
      $eval .= ');';
      eval($eval);
    }
    $stmt->execute();
    $this->stmt = $stmt;
    return true;
  }
  /**
   * 
   * @return type
   */
  public function getStmtAsArray(){
    $stmt = $this->stmt;
    $data = $this->data;
    if(isset($data['select'])){
      $eval = '$stmt->bind_result(';
      foreach ($data['select'] as $key => $value) {
        $eval .= '$result["'.$value.'"],';
      }
      $eval = substr($eval, 0, strlen($eval)-1);
      $eval .= ');';
      eval($eval);
      $array = array();
      while($row = $stmt->fetch()) {
        $temp = array();
        //foreach ($data['select'] as $key => $value) {
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
  /**
   * 
   * @return type
   */
  public function getStmt(){
    return $this->stmt;
  }
}
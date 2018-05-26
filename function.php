<?php
$host = '149.28.29.240:3306';
$user = 'root';
$pass = 'Pfsense@root';
$database = 'main';
$con = new mysqli($host, $user, $pass, $database);
mysqli_set_charset($con, "utf8");

//ล้าง money
function resetmoney() {
	global $con;
	$table = 'money';

	$sql = "DROP TABLE $table";
	$con->query($sql);

	$sql = "CREATE TABLE $table (
	id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(255),
	lineid VARCHAR(255),
	net INT
	)";
	if (!$con->query($sql)){
		echo $con->error;
	}
}

//ล้าง database
function resetpoke() {
	global $con;
	$table = 'poke';

	$sql = "DROP TABLE $table";
	$con->query($sql);

	$sql = "CREATE TABLE $table (
	id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(255),
	lineid VARCHAR(255),
	content VARCHAR(255),
	net INT
	)";
	if (!$con->query($sql)){
		echo $con->error;
	}

	$data = array('status' => 'down', 'lap' => 0, 'result' => '', 'player' => '4', 'min' => '20', 'max' => '200');
	$data = json_encode($data);

	$sql = "INSERT INTO $table (name, lineid, content) 
	VALUES ('\"index\"', 'index', '$data')";
	if (!$con->query($sql)){
		echo $con->error;
	}
}

//รับค่าจาก database
function select($table, $column, $key, $value) {
	global $con;
	$sql = "SELECT $column AS reply FROM $table WHERE $key='$value'";
	$result = $con->query($sql);
	$row = $result->fetch_assoc();
	return $row['reply'];
}

//ส่งค่าไป database
function update($table, $column, $text, $key, $value) {
	global $con;
	$sql = "UPDATE $table SET $column = '$text' WHERE $key = '$value'";
	if (!$con->query($sql)){
		echo $con->error;
		return false;
	}
	return true;
}

//ลบค่าที่เป็นวรรคและเอ็นเตอร์
function clear($text){
	$search = array(" ","
");
	$reply = str_replace($search, "", $text);
	return $reply;
}

//ทำให้ชื่อใส่ใน db ได้
function codename($text){
	$reply = '';
	$text = json_encode($text);
	$text = str_split($text);
	foreach($text as $v){
		if ($v == "\\"){
			$reply .= "\\\\";
		}
		else {
			$reply .= $v;
		}
	}
	return $reply;
}

//ใส่ข้อมูลใน db
function insert($table, $name, $lineid, $net=0) {
	global $con;
	$sql = "INSERT INTO $table (name, lineid, net) VALUES ('$name', '$lineid', $net)";
	if (!$con->query($sql)){
		echo $con->error;
	}
}

//รับค่า id
function getid($table, $lineid) {
	global $con;
	$sql = "SELECT id, lineid FROM $table";
	$result = $con->query($sql);
	while ($row = $result->fetch_assoc()){
		if ($row['lineid'][0] == "A"){
			$row['lineid'] = substr($row['lineid'], 1);
		}
		if ($lineid == $row['lineid']){
			return $row['id'];
		}
	}
	return 0;
}

//ตรวจแอดมิน
function checkadmin($table, $lineid) {
	global $con;
	$sql = "SELECT lineid FROM $table";
	$result = $con->query($sql);
	while ($row = $result->fetch_assoc()){
		if ($row['lineid'][0] == "A" && substr($row['lineid'], 1) == $lineid){
			return 1;
		}
	}
	return 0;
}

//check การแทง
function check($id, $player) {
	global $con;
	$sql = "SELECT content FROM poke WHERE id = $id";
	$result = $con->query($sql);
	$row = $result->fetch_assoc();
	$content = json_decode($row['content'], 1);
	$muti = ($content['muti'] == 'yes') ? 'เล่นเด้ง' : 'ไม่เล่นเด้ง';
	$reply = '';
	for ($i=1; $i<=$player; $i++) { 
		if ($content[$i] != '') {
			$reply .= " $i=" . $content[$i];
		}
	}
	if ($reply != ''){
		$reply = "แทง$reply $muti";
		return $reply;
	}
}

//checkall
function checkall($player) {
	global $con;
	$sql = "SELECT name, content FROM poke WHERE id > 1";
	$result = $con->query($sql);
	while ($row = $result->fetch_assoc()) {
		$reply = '';
		$content = json_decode($row['content'], 1);
		$name = json_decode($row['name'], 1);
		$muti = ($content['muti'] == 'yes') ? 'เล่นเด้ง' : 'ไม่เล่นเด้ง';
		for ($i=1; $i<=$player; $i++) {
			if ($content[$i] != '') {
				$reply .= " $i=" . $content[$i];
			}
		}
		if ($reply != ''){
		$report .= "
คุณ $name แทง$reply $muti";
		}
	}
	return $report;
}

//สรุป
function result() {
	global $con;
	$player_muti = [];
	$player_value = [];
	$reply = '';
	$data = select('poke', 'content', 'id', 1);
	$data = json_decode($data, 1);
	foreach ($data['result'] as $key => $value) {
		if ($key == 0) {
			$host_value = substr($value, 1);
		}
		else {
			$player_muti[$key] = $value[0];
			$player_value[$key] = substr($value, 1);
		}
	}
	$sql = "SELECT name, content, net FROM poke WHERE id > 1";
	$result = $con->query($sql);
	while ($row = $result->fetch_assoc()) {
		$content = json_decode($row['content'], 1);
		$check = '';
		foreach ($content as $key => $value) {
			if($key == 'muti') {
				continue;
			}
			$check .= $value;
		}
		if ($check == '') {
			continue;
		}
		$money = 0;
		$reply .= '
	คุณ ' . json_decode($row['name'], 1);
		foreach($player_muti as $key => $value) {
			if ($content['muti'] == 'yes') {
				$host_muti = $data['result'][0][0];
			}
			else {
				$host_muti = 1;
				$value = 1;
			}
			if ($content[$key] != 0) {
				if ($player_value[$key] > $host_value) {
					$money += $content[$key] * $value;
				}
				else if ($player_value[$key] < $host_value) {
					$money -= $content[$key] * $host_muti;
				}
			}
		}
		$net = $row['net']+$money;
		if ($money > 0) {
			$reply .= "ได้ $money=$net";
		}
		else if ($money == 0) {
			$reply .= "ไม่ได้ไม่เสีย";
		}
		else {
			$reply .= "เสีย $money=$net";
		}
		$replycontent = json_encode(array('muti' => $content['muti']));
		$sql2 = "UPDATE poke SET content = '$replycontent' WHERE name = '" . $row['name'] . "'";
		if(!$con->query($sql2)) {
			echo $con->error;
		}
		update('money', 'net', $net, 'name', $row['name']);
	}
	$reply = "สรุปรอบที่ " . $data['lap'] . $reply;
	return $reply;
}
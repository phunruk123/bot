<?php
$table = "poke";
$wrong = " รูปแบบการแทงผิด❌";
$access_token = 'MHvSIO19wEJ4VkvUIBQlh25t2v1qHyXb6SbYiZA5iYocokd2Zkp/kOOri+g37MDCyStLXw6SVEyP4d+xpU+pMwpoi5tCo+WoTK0cNvWQ/rHzZhilQq0ZFToQGLvibjzPEEm/XKehD9BL07bPs9EsbgdB04t89/1O/w1cDnyilFU=';

$content = file_get_contents('php://input');
$events = json_decode($content, true);
require('function.php');
if (!is_null($events['events'])) {
	foreach ($events['events'] as $event) {
		//รับค่าสำคัญจาก line
		$replyToken = $event['replyToken'];
		$text2 = $event['message']['text'];
		$type = $event['message']['type'];
		$packageid = $event['message']['packageId'];
		$stickerid = $event['message']['stickerId'];
		$lineid = $event['source']['userId'];
		$groupid = $event['source']['groupId'];
		$roomid = $event['source']['roomId'];

		//รับค่าสำคัญจาก datebase
		$data = select($table, 'content', 'id', 1);
		$net = select('money', 'net', 'lineid', $lineid);
		$content = select($table, 'content', 'id', $id);

		//ปรับค่าต่าง ๆ
		$data = json_decode($data, 1);
		$content = json_decode($content, 1);
		$text = clear($text2);
		$arr = explode(' ', $text2);

		//update ยอดเงิน
		update($table, 'net', $net, 'id', $id);

		//รับค่าชื่อ
		//ถ้าเป็นเพื่อน
		$url = 'https://api.line.me/v2/bot/profile/' . $lineid;
		$headers = array('Authorization: Bearer ' . $access_token);
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$result = curl_exec($ch);
		$res = json_decode($result, true);
		//ถ้าเป็นห้อง
		if ($res['userId'] == '' && $roomid != '') {
			$url = 'https://api.line.me/v2/bot/room/' . $roomid . '/member/' . $lineid;
			$headers = array('Authorization: Bearer ' . $access_token);
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			$result = curl_exec($ch);
			$res = json_decode($result, true);
		}
		//ถ้าเป็นกลุ่ม
		if ($res['userId'] == '' && $groupid != '') {
			$url = 'https://api.line.me/v2/bot/group/' . $groupid . '/member/' . $lineid;
			$headers = array('Authorization: Bearer ' . $access_token);
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			$result = curl_exec($ch);
			$res = json_decode($result, true);
		}
		$name = $res['displayName'];	//สร้างตัวแปรชื่อ

		//คำสั่งดู id
		if ($text == 'ALL') {
			$replytext = "Groupid:$groupid
Roomid:$roomid
Lineid:$lineid";
		}
		if ($text == 'Sh' || $text == 'sh') {
			$replytext = "Lineid:$lineid";
		}

		//ระบบแทง
		if ($text[0] == 'b' || $text[0] == 'B') {
			if ($data['status'] == 'open') {
				$text = substr($text, 1);
				$arr = explode(',', $text);
				$reply = [];
				$replytext = '';
				$value = 0;
				$replycontent = ['muti' => $content['muti']];
				for ($i=1; $i <= $data['player']; $i++) { 
					$replycontent[$i] = '';
				}
				foreach ($arr as $n => $v) {
					$var = explode("-", $v);
					$front = $var[0];
					$back = $var[1];
					if (is_numeric($front)) {
						if (is_numeric($back)) {
							if ($back >= $data['min'] && $back <= $data['max']) {
								if (0 < $front && $front <= $data['player']) {
									$replycontent[$front] = $back;
									$value += $back;
								}
								else if ($front > 10) {
									$fronts = str_split($front);
									foreach ($fronts as $f) {
										$replycontent[$f] = $back;
										$value += $back;
									}
								}
								else {
									$reply[$n] = "หมายเลขขา $v ผิด";
								}
							}
							else {
								$reply[$n] = "จำนวนเงิน $v ผิด";
							}
						}
						else {
							$reply[$n] = "รูปแบบเงิน $v ผิด";
						}
					}
					else {
						$reply[$n] = "หมายเลขขา $v ผิด";
					}
				}
				if ($content['muti'] == 'yes') {
					$muti = 2;
				}
				else {
					$muti = 1;
				}
				if ($net >= $value * $muti) {
					$replycontent = json_encode($replycontent);
					update($table, 'content', $replycontent, 'id', $id);
					for ($i=0; $i <= $n; $i++) {
						if($reply[$i] != ''){
							$replytext .= '
'.$reply[$i];
						}
					}
					if (clear($replytext) != '') {
						$replytext = "คุณ $name " . $replytext;
					}
				}
				else {
					$replytext = "คุณ $name เครดิตไม่เพียงพอ";
				}
			}
		}

		//check รายบุคคล
		if ($text == 'check' || $text == 'Check') {
			$reply = check($id, $data['player']);
			if ($reply == '') {
				$replytext = "คุณ $name ยังไม่ได้แทง";
			}
			else {
				$replytext = "คุณ $name
$reply";
			}
		}

		//คำสั่งเล่นเด้ง
		if ($text == 'เด้ง') {
			$replycontent = [];
			$replycontent['muti'] = 'yes';
			for ($i=1; $i <= $data['player']; $i++) { 
				$replycontent[$i] = $content[$i];
			}
			$replycontent = json_encode($replycontent);
			update($table, 'content', $replycontent, 'id', $id);
		}

		//คำสั่งไม่เล่นเด้ง
		if ($text == 'ไม่เด้ง') {
			$replycontent = [];
			$replycontent['muti'] = 'no';
			for ($i=1; $i <= $data['player']; $i++) { 
				$replycontent[$i] = $content[$i];
			}
			$replycontent = json_encode($replycontent);
			update($table, 'content', $replycontent, 'id', $id);
		}

		//ดูยอดเงิน
		if ($text == '@id') {
			$replytext = "คุณ $name เครดิตคงเหลือ $net";
		}

//---------------------------------------คำสั่งของ admin--------------------------------------------//
		//คำสั้งเปิดบ้าน
		if ($type == 'sticker' && $packageid == '2000003' && $stickerid == '48844') {
			if (checkadmin($table, $lineid) == 1) {
				if ($data['status'] == 'down') {
					$replytext = 'เปิดบ้านแล้ว';
					$replydata = [];
					$replydata['status'] = 'close';
					$replydata['lap'] = '0';
					$replydata['result'] = $data['result'];
					$replydata['player'] = $data['player'];
					$replydata['min'] = $data['min'];
					$replydata['max'] = $data['max'];
					$replydata = json_encode($replydata);
					update($table, 'content', $replydata, 'id', 1);
				}
				else {
					$replytext = 'เปิดบ้านแล้ว';
				}
			}
		}

		//คำสั่งเปิดรอบ
		if ($type == 'sticker' && $packageid == '2000004' && $stickerid == '48982') {
			if (checkadmin($table,$lineid) == 1) {
				if ($data['status']=='close') {
					$manymessage = 2;
					$lap = $data['lap'] + 1;
					$replytext1 = 'ยังไม่มีกติกาในขณะนี้';
					$replytext2 = "เปิดรอบที่$lap";
					$replydata = [];
					$replydata['status'] = 'open';
					$replydata['lap'] = $lap;
					$replydata['result'] = $data['result'];
					$replydata['player'] = $data['player'];
					$replydata['min'] = $data['min'];
					$replydata['max'] = $data['max'];
					$replydata = json_encode($replydata);
					update($table, 'content', $replydata, 'id', 1);
				}
				else if ($data['status'] == 'open') {
					$replytext = 'เปิดรอบแล้ว';
				}
				else if ($data['status'] == 'down') {
					$replytext = 'บ้านปิดอยู่กรุณาเปิดบ้านก่อน';
				}
				else {
					$replytext = 'กรุณาสรุปผลให้เรียบร้อยก่อน';
				}
			}
		}

		//คำสั่งปิดรอบ
		if ($type == 'sticker' && $packageid == '2000004' && $stickerid == '48988') {
			if (checkadmin($table, $lineid) == 1) {
				if ($data['status'] == 'open') {
					$manymessage = 2;
					$replytext1 = 'ยังไม่มีกติกาในขณะนี้';
					$replytext2 = 'ปิดรอบที่' . $data['lap'];
					$replydata = [];
					$replydata['status'] = 'check';
					$replydata['lap'] = $data['lap'];
					$replydata['result'] = $data['result'];
					$replydata['player'] = $data['player'];
					$replydata['min'] = $data['min'];
					$replydata['max'] = $data['max'];
					$replydata = json_encode($replydata);
					update($table, 'content', $replydata, 'id', 1);
				}
				else if ($data['status'] == 'down') {
					$replytext = 'บ้านปิดอยู่กรุณาเปิดบ้านก่อน';
				}
				else {
					$replytext = 'ปิดรอบแล้ว';
				}
			}
		}

		//คำสั่งปิดบ้าน
		if ($type == 'sticker' && $packageid == '2000003' && $stickerid == '48843') {
			if (checkadmin($table, $lineid) == 1) {
				if ($data['status'] == 'close') {
					$replytext = 'ปิดบ้านแล้ว';
					$replydata = [];
					$replydata['status'] = 'check';
					$replydata['lap'] = '0';
					$replydata['result'] = $data['result'];
					$replydata['player'] = $data['player'];
					$replydata['min'] = $data['min'];
					$replydata['max'] = $data['max'];
					$replydata = json_encode($replydata);
					update($table, 'content', $replydata, 'id', 1);
				}
				else {
					$replytext = 'ปิดบ้านแล้ว';
				}
			}
		}

		//check ข้อมูล
		if ($text == 'checkall' || $text == 'Checkall' || $text == 'Ca' || $text == 'ca') {
			if (checkadmin($table, $lineid) == 1) {
				$reply = checkall($data['player']);
				if ($reply == '') {
					$replytext = 'ยังไม่มีใครแทง';
				}
				else {
					$replytext = 'รอบที่ ' . $data['lap'] . $reply;
				}
			}
		}

		//สรุปผล
		if ($text[0] == 'r' || $text[0] == 'R') {
			if (checkadmin($table, $lineid) == 1) {
				if ($data['status'] == 'check') {
					$replytext = '';
					$text = substr($text, 1);
					$texts = explode(',', $text);
					for ($n=0; $n<=$data['player']; $n++) {
						$v = substr($texts[$n], 1);
						if ($n == 0) {
							continue;
						}
						if ($texts[$n][0] == 1) {
							$replytext .= "
ขา$n ".$v."แต้มไม่เด้ง";
						}
						else if ($texts[$n][0] == 2){
							$replytext .= "
ขา$n ".$v."แต้มเด้ง";
						}
						else {
							$replytext .= "
ขา$n สรุปผิด";
						}
					}
					$host_value = substr($texts[0], 1);
					if ($texts[0][0] == 1) {
						$replytext = "ขาเจ้า " . substr($texts[0], 1) . "แต้มไม่เด้ง" . $replytext;
					}
					else if ($texts[0][0] == 2) {
						$replytext = "ขาเจ้า " . substr($texts[0], 1) . "แต้มเด้ง" . $replytext;
					}
					else {
						$replytext = "ขาเจ้า สรุปผิด" . $replytext;
					}
					$replydata = [];
					$replydata['status'] = 'check';
					$replydata['lap'] = $data['lap'];
					$replydata['result'] = $texts;
					$replydata['player'] = $data['player'];
					$replydata['min'] = $data['min'];
					$replydata['max'] = $data['max'];
					$replydata = json_encode($replydata);
					update($table, 'content', $replydata, 'id', 1);
				}
				else {
					$replytext = 'กรุณาปิดรอบก่อน';
				}
			}
		}

		//ยืนยันผล
		if ($text == '@ok') {
			if (checkadmin($table, $lineid) == 1 && $data['status'] == 'check') {
				$replytext = '';
				foreach ($data['result'] as $key => $value) {
					if ($key == '0') {
						$key = 'เจ้า';
					}
					$front = $value[0];
					$back = substr($value, 1);
					if (!is_numeric($front) || !is_numeric($back) || ($front != 1 && $front != 2) || 0 > $back || $back > 9) {
						$replytext .= "$key ";
					}
				}
				if (clear($replytext) != '') {
					$replytext = 'สรุปขา '. $replytext . 'ผิด';
				}
				else {
					$replytext = result();
					$replydata = [];
					$replydata['status'] = 'close';
					$replydata['lap'] = $data['lap'];
					$replydata['result'] = '';
					$replydata['player'] = $data['player'];
					$replydata['min'] = $data['min'];
					$replydata['max'] = $data['max'];
					$replydata = json_encode($replydata);
					update($table, 'content', $replydata, 'id', 1);
				}
			}
		}

		//ปรับชื่อ
		if ($text == 'ปรับ') {
			if (checkadmin($table, $lineid) == 1) {
				$sql="SELECT id, lineid FROM $table WHERE id > 1";
				$result = $con->query($sql);
				while ($row = $result->fetch_assoc()) {
					$url = 'https://api.line.me/v2/bot/profile/' . $lineid;
					$headers = array('Authorization: Bearer ' . $access_token);
					$ch = curl_init($url);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
					curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
					$result = curl_exec($ch);
					$res = json_decode($result, true);
					//ถ้าเป็นห้อง
					if ($res['userId'] == '' && $roomid != '') {
						$url = 'https://api.line.me/v2/bot/room/' . $roomid . '/member/' . $lineid;
						$headers = array('Authorization: Bearer ' . $access_token);
						$ch = curl_init($url);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
						curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
						$result = curl_exec($ch);
						$res = json_decode($result, true);
					}
					//ถ้าเป็นกลุ่ม
					if ($res['userId'] == '' && $groupid != '') {
						$url = 'https://api.line.me/v2/bot/group/' . $groupid . '/member/' . $lineid;
						$headers = array('Authorization: Bearer ' . $access_token);
						$ch = curl_init($url);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
						curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
						$result = curl_exec($ch);
						$res = json_decode($result, true);
					}
					if ($res['userId'] != '') {
						update($table, 'name', codename($res['displayName']), 'lineid', $row['lineid']);
					}
				}
				$replytext = 'ปรับเสร็จสิ้น';
			}
		}

		//เติมเงิน
		if ($arr[0] == '@เติม'){
			if (checkadmin($table, $lineid) == 1) {
				$user_net = select($table, 'net', 'name', codename(substr($arr[1], 1)));
				if(update('money', 'net', $user_net + $arr[2], 'name', codename(substr($arr[1], 1)))){
					$replytext = "เติมเครดิตให้คุณ $arr[1] จำนวน $arr[2]=" . ($user_net + $arr[2]);
				}
				else {
					$replytext = 'เติมเครดิตไม่สำเร็จ';
				}
			}
		}

		//ถอนเงิน
		if ($arr[0] == '@ถอน'){
			if (checkadmin($table, $lineid) == 1) {
				$user_net = select($table, 'net', 'name', codename(substr($arr[1], 1)));
				if(update('money', 'net', $user_net - $arr[2], 'name', codename(substr($arr[1], 1)))){
					$replytext = "ถอนเครดิตให้คุณ $arr[1] จำนวน $arr[2]=" . ($user_net - $arr[2]);
				}
				else {
					$replytext = 'เติมเครดิตไม่สำเร็จ';
				}
			}
		}
//---------------------------------------คำสั่งของ admin--------------------------------------------//

		//สมัครเล่น
		if ($id == 0 && $res['userId'] != '') {
			insert($table, codename($name), $lineid);
			update($table, 'content', '{"muti":"yes"}', 'lineid', $lineid);
			if (getid('money', $lineid) == 0) {
				insert('money', codename($name), $lineid);
			}
		}

		//สร้างข้อความตอบกลับ
		$messages = [
			[
			'type' => 'text',
			'text' => $replytext
			]
		];
		if ($manymessage == 2){
			$messages = [
				[
				'type' => 'text',
				'text' => $replytext1
				],
				[
				'type' => 'text',
				'text' => $replytext2
				]
			];
		}
		$url = 'https://api.line.me/v2/bot/message/reply';
		$data = [
			'replyToken' => $replyToken,
			'messages' => $messages,
		];
		$post = json_encode($data);
		$headers = array('Content-Type: application/json', 'Authorization: Bearer ' . $access_token);
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);	
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);	
		curl_exec($ch);
	}
}
echo 'OK Reply';
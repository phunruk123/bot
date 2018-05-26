<?php
$table = "poke";
$access_token = 'Z8Diikrg1RLZu/AP9mPjrjgIwUKRhgam8aoSEYxT5nrzq+DjHIcWrh23J3DMVB7mTNVqt1py8xBXipDRvpXUCgDvv8GJV3yMIb6BZl88wHZSiOY+DJm1aqi8fE1iV8ObBigJLxUz6RKDcYGfacP0RQdB04t89/1O/w1cDnyilFU=';

$content = file_get_contents('php://input');
$events = json_decode($content, true);
require('function.php');
// resetPoke();
// resetMoney();
if (!is_null($events['events'])) {
	foreach ($events['events'] as $event) {
		$replyToken = $event['replyToken'];
		$groupId = $event['source']['groupId'];

		//ล็อคห้องไลน์
		if (in_array($groupId, [''])) {

			//รับค่าสำคัญจาก line
			$type = $event['message']['type'];
			$text2 = $event['message']['text'];
			$packageId = $event['message']['packageId'];
			$stickerId = $event['message']['stickerId'];
			$lineId = $event['source']['userId'];
			$roomid = $event['source']['roomId'];

			//หา id จาก lineId
			$id = getId($table, $lineId);

			//รับค่าสำคัญจาก database
			$data = select($table, 'content', 'id', 1);
			$net = select('money', 'net', 'lineId', $lineId);
			$content = select($table, 'content', 'id', $id);
			$admin = select($table, 'admin', 'id', $id);

			//ปรับค่าต่าง ๆ
			$data = json_decode($data, 1);
			$content = json_decode($content, 1);
			$text = clear($text2);
			$arr = explode(' ', $text2);
			$thirdText = substr($text, 0, 3);

			//update ยอดเงิน
			update($table, 'net', $net, 'id', $id);

			/*รับค่าชื่อ*/
			//ถ้าเป็นเพื่อน
			$url = 'https://api.line.me/v2/bot/profile/' . $lineId;
			$headers = array('Authorization: Bearer ' . $access_token);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			$result = curl_exec($ch);
			$res = json_decode($result, true);
			//ถ้าเป็นห้อง
			if ($res['userId'] == '' && $roomid != '') {
				$url = 'https://api.line.me/v2/bot/room/' . $roomid . '/member/' . $lineId;
				curl_setopt($ch, CURLOPT_URL, $url);
				$result = curl_exec($ch);
				$res = json_decode($result, true);
			}
			//ถ้าเป็นกลุ่ม
			if ($res['userId'] == '' && $groupId != '') {
				$url = 'https://api.line.me/v2/bot/group/' . $groupId . '/member/' . $lineId;
				curl_setopt($ch, CURLOPT_URL, $url);
				$result = curl_exec($ch);
				$res = json_decode($result, true);
			}
			curl_close($ch);
			$name = $res['displayName'];	//สร้างตัวแปรชื่อ

			//สมัครเล่น
			if ($id == '0' && $res['userId'] != '') {
				insert($table, codeName($name), $lineId);
				update($table, 'content', '{"muti":"yes"}', 'lineId', $lineId);
				if (getid('money', $lineId) == 0) {
					insert('money', codeName($name), $lineId);
				}
				$net = 0;
				$id = select($table, 'id', 'lineId', $lineId);
			}

			//คำสั่งดู id
			if (in_array($text, ['ox', 'Ox'])) {
				$replyText = "id:$id\r\nlineId:$lineId\r\nuserId:".$res['userId'];
			}

			//ระบบแทง
			if (in_array($text[0], ['t', 'T'])) {
				$text = substr($text, 1);
				$arr = explode(',', $text);
				$reply = [];
				$replyText = '';
				$newNet = 0;
				foreach ($arr as $n => $v) {
					$var = explode("-", $v);
					$front = $var[0];
					$back = $var[1];
					if (is_numeric($front)) {
						if (is_numeric($back)) {
							if ($back >= $data['min'] && $back <= $data['max']) {
								if (0 < $front && $front <= $data['player']) {
									$content[$front] = $back;
								}
								else if ($front > 10) {
									$fronts = str_split($front);
									foreach ($fronts as $f) {
										$content[$f] = $back;
									}
								}
								else {
									$reply[$n] = "การแทง $v ขาไม่ถูกต้อง มีขา 1-".$data['player'].' เท่านั้น❗';
								}
							}
							else {
								$reply[$n] = "การแทง $v ไม่ถูกต้อง วงเงินการแทง ".$data['min'].'-'.$data['max'].' บาท❗';
							}
						}
						else {
							$reply[$n] = "การแทง $v ไม่ถูกต้อง❗";
						}
					}
					else {
						$reply[$n] = "การแทง $v ขาไม่ถูกต้อง มีขา 1-".$data['player'].' เท่านั้น❗';
					}
				}
				foreach ($content as $key => $value) {
					if ($key == 'muti') {
						continue;
					}
					$newNet += $value;
				}
				if ($content['muti'] == 'yes') {
					$newNet *= 2;
				}
				if ($net >= $newNet) {
					for ($i=0; $i <= $n; $i++) {
						if ($reply[$i] != ''){
							$replyText .= $reply[$i];
							if ($i != $n) {
								$replyText .= "\r\n";
							}
						}
					}
					if (clear($replyText) != '') {
						$replyText = "$name " . $replyText;
					}
				}
				else if ($content['muti'] == 'yes') {
					$replyText = "$name เครดิตไม่พอต่อการแทงเด้ง❗";
				}
				else {
					$replyText = "$name เครดิตไม่เพียงพอ❗";
				}
				if ($data['status'] == 'open') {
					if (clear($replyText) == '') {
						$content = json_encode($content);
						update($table, 'content', $content, 'id', $id);
						//การตอบกลับ
						if ($data['reply'] == 'yes') {
							$reply = check($id, $data['player'], $data['muti']);
							if (clear($replyText) == '') {
								$replyText = "$name $reply";
							}
							else {
								$replyText .= "\r\n$reply";
							}
						}
					}
				}
				else if (clear($replyText) == '') {
					if ($data['status'] == 'check') {
						$replyText = "$name แทงไม่ทัน❗";
					}
					else if ($data['status'] == 'close') {
						$replyText = "$name ยังไม่เปิดรอบ❗";
					}
					else {
						$replyText = "$name ปิดบ้าน❗";
					}
				}
				else {
					unset($replyText);
				}
			}

			//ระบบยกเลิกแทง
			if (in_array($text[0], ['x', 'X'])) {
				$player = substr($text, 1);
				for($i=0; $i<$data['player']; $i++) {
					if (is_numeric($player[$i]) && (0<$player[$i] || $player[$i]<$data['player'])) {
						$content[$player[$i]] = '';
					}
				}
				$content = json_encode($content);
				update($table, 'content', $content, 'id', $id);
				//ระบบตอบกลับ
				if ($data['reply'] == 'yes') {
					$reply = check($id, $data['player'], $data['muti']);
					if ($reply == '') {
						$replyText = "$name ไม่ได้แทง";
					}
					else {
						$replyText = "$name $reply";
					}
				}
			}

			//check รายบุคคล
			if (in_array($text, ['check', 'Check', 'Ch', 'CH', 'c', 'เช็ค'])) {
				$reply = check($id, $data['player'], $data['muti']);
				if ($reply == '') {
					$replyText = "$name ไม่ได้แทง";
				}
				else {
					$replyText = "$name $reply";
				}
			}

			//เล่นเด้ง
			if ($data['muti'] == 'yes') {
				//คำสั่งเล่นเด้ง
				if ($text == 'เล่นเด้ง') {
					$price = 0;
					foreach ($content as $key => $value) {
						if ($key == 'muti') {
							continue;
						}
						$price += $value;
					}
					if ($net >= ($price * 2)) {
						$content['muti'] = 'yes';
						$content = json_encode($content);
						update($table, 'content', $content, 'id', $id);
						if ($data['reply'] == 'yes') {
							$replyText = "$name เล่นเด้ง";
						}
					}
					else {
						$replyText = "$name เครดิตไม่พอต่อการเล่นเด้ง❗";
					}
				}

				//คำสั่งไม่เล่นเด้ง
				if ($text == 'ไม่เด้ง') {
					$content['muti'] = 'no';
					$content = json_encode($content);
					update($table, 'content', $content, 'id', $id);
					if ($data['reply'] == 'yes') {
						$replyText = "$name ไม่เล่นเด้ง";
					}
				}
			}

			//ดูยอดเงิน และการส่งรูป
			if ($type == 'image' || $thirdText == '@id') {
				$replyText = "$name ID คือ $id เครดิตคงเหลือ $net";
				update($table, 'name', codeName($name), 'id', $id);
				update('money', 'name', codeName($name), 'lineId', $lineId);
				//ไม่ต้องตอบ id ต่อไปนี้
				if (in_array($id, [])) {
					unset($replyText);
				}
			}

	//---------------------------------------คำสั่งของ admin--------------------------------------------//
			if ($admin == 'yes') {

				if ($text == 'ALL') {
					$replyText = "groupId:$groupId\r\nRoomid:$roomid\r\nlineId:$lineId";
				}

				// //เช็ค sticker
				// if ($type == 'sticker') {
				// 	$replyText = "$stickerId\r\n$packageId";
				// }

				//คำสั้งเปิดเกม
				if ($type == 'sticker' && $packageId == '2000003' && $stickerId == '48844') {
					if ($data['status'] == 'down') {
						$game = ($data['game'] == 1) ? 1 : $data['game'] + 1;
						$replyText = "เริ่มเกมที่ #".$game;
						$data['status'] = 'close';
						$data['game'] = $game;
						$data['bigLap'] = 1;
						$data['lap'] = 1;
						$data = json_encode($data);
						update($table, 'content', $data, 'id', 1);
					}
				}

				//คำสั่งเปิดรอบ
				if ($type == 'sticker' && $packageId == '2000004' && $stickerId == '48982') {
					if ($data['status']=='close') {
						$manymessage = 2;
						$lap = ($data['lap'] == 1) ? 1 : $data['lap'] + 1;
						$replyText1 = '♠♥ การแทง ♦♣

พิมพ์ T ตามด้วยขาที่จะเล่น แล้ว ขีด (-)  จำนวนเงิน เช่น T123456-200 คือ แทงขา 1,2,3,4,5,6 ขาละ 200 บาท

สามารถกำหนดจำนวนเงินแทงแต่ละขาไม่เท่ากันได้
T1-20 T2-30 T3-40 T3-50
หรือ
T1-20,2-30,3-40,4-50,5-60,6-70';
						$replyText2 = "เปิดรอบย่อยที่ #$lap";
						$data['status'] = 'open';
						$data['lap'] = $lap;
						$data = json_encode($data);
						update($table, 'content', $data, 'id', 1);
					}
					else if ($data['status'] == 'open') {
						$replyText = 'เปิดรอบแล้ว';
					}
					else if ($data['status'] == 'down') {
						$replyText = 'โปรดเช็คสถานะ';
					}
					else {
						$replyText = 'กรุณาสรุปผลให้เรียบร้อยก่อน';
					}
				}

				//คำสั่งปิดรอบ
				if ($type == 'sticker' && $packageId == '2000004' && $stickerId == '48988') {
					if ($data['status'] == 'open') {
						$manymessage = 2;
						$replyText1 = '♠♥ แต้มพิเศษ ♣️♦️

ไพ่ 7.5 แต้ม  2 เด้ง 
55, 1010, JJ, QQ, KK
ไพ่ 7.5 แต้ม  2 เด้ง (ดอกเดียวกัน)
J♦️Q♦️, J♣️K♣️, Q♠️K♠️ 
ไพ่ 7.5 แต้ม  
JQ♥♠, JK♦️♠, QK♦️♠


เช็คยอดเงินพิมพ์ "@id"

🏧ฝากเงิน 24 ชั่วโมง
เลขที่บัญชี
xxxx
พร้อมเพย์
xxxx
🚩ถอนเงินแจ้ง พพ. ก่อนปิดรอบ 10 นาที/หลังปิด15 นาที';
						$replyText2 = 'ปิดรอบย่อยที่ #' . $data['lap'];
						$data['status'] = 'check';
						$checkAllWage = checkAllWage($table);
						report('reportPoke', $data['game'], $data['bigLap'], $data['lap'], ['wage'=>$checkAllWage]);
						$data = json_encode($data);
						update($table, 'content', $data, 'id', 1);
					}
					else if ($data['status'] == 'down') {
						$replyText = 'โปรดเช็คสถานะ';
					}
					else {
						$replyText = 'ปิดรอบแล้ว';
					}
				}

				//คำสั่งพัก
				if ($type == 'sticker' && $packageId == '2000004' && $stickerId == '48963') {
					if ($data['status'] == 'close') {
						$bigLap = $data['bigLap'];
						$replyText = 'ปิดรอบใหญ่ที่ #'.$bigLap;
						$data['lap'] = 0;
						$data['bigLap'] = $bigLap + 1;
						$data = json_encode($data);
						update($table, 'content', $data, 'id', 1);
						$sentText = 'ทดสอบการตอบกลับ';
						send($access_token, 'Cb8a3124f5b0ca244d18d93e8cf0a6719', $sentText);
					}
					else {
						$replyText = 'โปรดเช็คสถานะ';
					}
				}

				//คำสั่งปิดเกม
				if ($type == 'sticker' && $packageId == '2000003' && $stickerId == '48843') {
					if ($data['status'] == 'close') {
						$replyText = 'ปิดบ้านแล้ว';
						$data['status'] = 'down';
						$data['lap'] = '0';
						$data['bigLap'] = '1';
						$data = json_encode($data);
						update($table, 'content', $data, 'id', 1);
					}
					else {
						$replyText = 'ปิดบ้านแล้ว';
					}
				}

				//check ข้อมูล
				if (in_array($text, ['checkall', 'Checkall', 'Ca', 'ca', 'a', 'สรุป'])) {
					$reply = checkAll($data['player'], $data['muti']);
					if ($reply == '') {
						$replyText = 'ยังไม่มีผู้เล่นแทง';
					}
					else {
						$replyText = 'สรุปการแทงทั้งหมดรอบที่ ' . $data['lap'] . $reply;
					}
				}

				//สรุปผล
				if (in_array($text[0], ['s', 'S'])) {
					if ($data['status'] == 'check') {
						$replyText = '';
						$text = substr($text, 1);
						$texts = explode(',', $text);
						for ($n=0; $n<=$data['player']; $n++) {
							$v = substr($texts[$n], 1);
							if ($n == 0) {
								continue;
							}
							if ($texts[$n][0] == 1) {
								$replyText .= "\r\nขา$n ".$v."แต้มไม่เด้ง";
							}
							else if ($texts[$n][0] == 2){
								$replyText .= "\r\nขา$n ".$v."แต้มเด้ง";
							}
							else {
								$replyText .= "\r\nขา$n สรุปผิด";
							}
						}
						$host_value = substr($texts[0], 1);
						if ($texts[0][0] == 1) {
							$replyText = "ขาเจ้า " . substr($texts[0], 1) . "แต้มไม่เด้ง\r\n" . $replyText;
						}
						else if ($texts[0][0] == 2) {
							$replyText = "ขาเจ้า " . substr($texts[0], 1) . "แต้มเด้ง\r\n" . $replyText;
						}
						else {
							$replyText = "ขาเจ้า สรุปผิด\r\n" . $replyText;
						}
						$replyText .= "\r\n\r\nยันยืนผลสรุป @ok";
						$data['status'] = 'check';
						$data['lap'] = $data['lap'];
						$data['result'] = $texts;
						$data = json_encode($data);
						update($table, 'content', $data, 'id', 1);
					}
					else {
						$replyText = 'กรุณาปิดรอบก่อน';
					}
				}

				//ยืนยันผล @ok
				if (in_array($text, ['@ok', '@OK', '@Ok'])) {
					$replyText = '';
					foreach ($data['result'] as $key => $value) {
						if ($key == '0') {
							$key = 'เจ้า';
						}
						$front = $value[0];
						$back = substr($value, 1);
						if (!is_numeric($front) || !is_numeric($back) || ($front != 1 && $front != 2) || 0 > $back || $back > 9) {
							$replyText .= "$key ";
						}
					}
					if (clear($replyText) != '') {
						$replyText = 'สรุปขา '. $replyText . 'ผิด';
					}
					else {
						$replyText = result();
						$data['status'] = 'close';
						$data['result'] = '';
						$data = json_encode($data);
						update($table, 'content', $data, 'id', 1);
					}
				}

				//เติมเงิน
				if ($text[0] == '$'){
					$vars = explode('+', $text);
					$memberId = substr($vars[0], 1);
					$operator = '+';
					$net = $vars[1];
					if (!isset($vars[1])) {
						$vars = explode('-', $text);
						$memberId = substr($vars[0], 1);
						$operator = '-';
						$net = $vars[1];
					}
					$userlineId = select($table, 'lineId', 'id', $memberId);
					$userName = select($table, 'name', 'id', $memberId);
					$userName = json_decode($userName, 1);
					$userNet = select('money', 'net', 'lineId', $userlineId);
					if (isset($userNet) && $userlineId != '') {
						if ($operator == '+') {
							$preWord = 'เพิ่ม';
							$remaining = $userNet + $net;
							$tablePath = 'deposite';
							if ($vars[2] != '') {
								$percent = substr($vars[2], 0, strlen($vars[2]));
								$bonus = $net*$percent/100;
								$remaining += $bonus;
								$bonusText = sprintf('โบนัส+%d ', $bonus);
							}
						}
						else {
							$preWord = 'ลด';
							$remaining = $userNet - $net;
							$tablePath = 'withdraw';
						}	
						if(update('money', 'net', $remaining, 'lineId', $userlineId)) {
							update('poke', 'net', $remaining, 'lineId', $userlineId);
							report('reportPoke', $data['game'], $data['bigLap'], $data['lap'], [$tablePath=>$net]);
							$replyText = "{$preWord}เครดิตคุณ {$userName} {$operator}{$net} $bonusText= " . $remaining;
						}
					}
					else {
						$replyText = 'โปรดระบุเลข id ให้ถูกต้อง';
					}
				}
			}
//---------------------------------------คำสั่งของ admin--------------------------------------------//
		}
		else {
			$replyText = "สวัสดีค่ะ ท่านลูกค้า 
หากท่านสนใจใช้บริการของเรา
ติดต่อด้านล่างนะคะ
Site:http://www.rbtech.co.th
(ระบบ สำหรับกลุ่ม phunruk เท่านั้น)
groupId : $groupId";
		}
	}
	//สร้างข้อความตอบกลับ
	$messages = [
		[
		'type' => 'text',
		'text' => $replyText
		]
	];
	if ($manymessage == 2){
		$messages = [
			[
			'type' => 'text',
			'text' => $replyText1
			],
			[
			'type' => 'text',
			'text' => $replyText2
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
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);	
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);	
	curl_exec($ch);
	curl_close($ch);
}
echo "\r\nConnectSuccess";

<?php
$data = [
  "type" => "Student/SR/PWD",
  "origin" => "Rizal / Pob Sur Terminal",
  "dest" => "Cabanatuan Central Terminal",
  "fare" => "12.00",
  "passenger_id" => null,
  "passenger_name" => null,
  "passenger_id_number" => null,
  "discount_verified" => false
];
$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data)
    ]
];
$context  = stream_context_create($options);
$result = file_get_contents('http://localhost/PARE/kiosk/process_ticket.php', false, $context);
echo "Response:\n$result\n";

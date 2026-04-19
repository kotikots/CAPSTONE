<?php
$data = [
    'type' => 'Regular',
    'origin' => 'Rizal / Pob Sur Terminal',
    'dest' => 'Cabanatuan Central Terminal',
    'fare' => 15.00,
    'passenger_id' => null,
    'passenger_name' => null
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
echo "Response:\n";
echo $result;

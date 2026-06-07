<?php

include '../dbutil.php';

// Get the request method
$method = $_SERVER['REQUEST_METHOD'];

// Get the requested endpoint
$endpoint = $_SERVER['PATH_INFO'];

// Set the response content type
header('Content-Type: application/json');

// Process the request
switch ($method) {
    case 'GET':
        if ($endpoint === '/notes') {
            $notes = getAllNotes($mysqli);
            echo json_encode($notes);
        } elseif (preg_match('/^\/notes\/(\d+)$/', $endpoint, $matches)) {
            $noteId = $matches[1];
            $note = getNoteById($noteId, $mysqli);
            echo json_encode($note);
        }
        break;
    // PUT or DELETE stuff if you want
}

// i need to pass mysqli along because the include is a different dir up
function getAllNotes($mysqli) {
	$query = "select id, title, note, dt_created, dt_updated
	          from notes
	          order by dt_updated desc";
	$query_run = mysqli_query($mysqli, $query);
	$notes = [];
	while ($row = mysqli_fetch_assoc($query_run)) {
		$row['note'] = decrypt_note($row['note']);
		$notes[] = $row;
	}
	return $notes;
}

function getNoteById($noteId, $mysqli) {
	$query = $mysqli->prepare("select id, title, note, dt_created, dt_updated
	          from notes
	          where id = ?
	          order by dt_updated desc");
	$query->bind_param("i", $noteId);
    $query->execute();
    $query_run = $query->get_result();
	$note = mysqli_fetch_array($query_run);
    $note['note'] = decrypt_note($note['note']);

	return $note;
}

?>
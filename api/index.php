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
        } else {
            http_response_code(400);
            echo json_encode(["status" => 400, "error" => "Bad Request", "message" => "Endpoint invalid or not found."]);
            exit();
        }
        break;
    case 'POST':
        if ($endpoint === '/notes') {

            $inputData = getRequestData();

            $title = $inputData['title'] ?? null;           // if title isn't sent in request, null
            if (trim($title) === '') { $title = null; }     // if title is sent but empty, null
            $note = $inputData['note'] ?? null;

            // title is nullable, note content is not
            if ($note === null || trim($note) === '') {
                http_response_code(422);
                echo json_encode(["status" => 422,"error" => "Validation Error","message" => "The note content is required and cannot be empty."]);
                exit();
            }

            $eNote = encrypt_note($note);

            // prepare is better for sql injection than inline string concats
            $query = $mysqli->prepare("INSERT INTO notes (title, note, dt_created, dt_updated)
                                       VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $query->bind_param("ss", $title, $eNote);
            $query_run = $query->execute();

            if ($query_run) {
                // grab the auto-generated id for this connection
                $newNoteId = $mysqli->insert_id;
                
                $fetchQuery = $mysqli->prepare("SELECT id, title, note, dt_created, dt_updated FROM notes WHERE id = ?");
                $fetchQuery->bind_param("i", $newNoteId);
                $fetchQuery->execute();
                $result = $fetchQuery->get_result();
                $newNote = $result->fetch_assoc();

                if ($newNote && !empty($newNote['note'])) {
                    $newNote['note'] = decrypt_note($newNote['note']);
                }

                http_response_code(201);
                echo json_encode(["status" => 201, "message" => "Note created successfully", "data" => $newNote]);
                exit();
            } else {
                http_response_code(500);
                echo json_encode(["status" => 500, "error" => "Error creating new note", "message" => "The note was not created."]);
                exit();
            }

        } else {
            http_response_code(400);
            echo json_encode(["status" => 400, "error" => "Bad Request", "message" => "Endpoint invalid or not found."]);
            exit();
        }
        break;
    case 'PUT':
        if (preg_match('/^\/notes\/(\d+)$/', $endpoint, $matches)) {
            $noteId = $matches[1];

            $inputData = getRequestData();
            
            $title = $inputData['title'] ?? null;           // if title isn't sent in request, null
            if (trim($title) === '') { $title = null; }     // if title is sent but empty, null
            $note = $inputData['note'] ?? null;

            // title is nullable, note content is not
            if ($note === null || trim($note) === '') {
                http_response_code(422);
                echo json_encode(["status" => 422,"error" => "Validation Error","message" => "The note content is required and cannot be empty."]);
                exit();
            }

            $eNote = encrypt_note($note);

            $query = $mysqli->prepare("UPDATE notes SET title=?, note=?, dt_updated = CURRENT_TIMESTAMP WHERE id=?");
            $query->bind_param("ssi", $title, $eNote, $noteId);
            $query_run = $query->execute();

            if ($query_run) {
                $fetchQuery = $mysqli->prepare("SELECT id, title, note, dt_created, dt_updated FROM notes WHERE id = ?");
                $fetchQuery->bind_param("i", $noteId);
                $fetchQuery->execute();
                $result = $fetchQuery->get_result();
                $newNote = $result->fetch_assoc();

                if ($newNote && !empty($newNote['note'])) {
                    $newNote['note'] = decrypt_note($newNote['note']);
                }

                http_response_code(200);
                echo json_encode(["status" => 200, "message" => "Note updated successfully", "data" => $newNote]);
                exit();
            } else {
                http_response_code(500);
                echo json_encode(["status" => 500, "error" => "Error updating note", "message" => "The note was not updated."]);
                exit();
            }

        } else {
            http_response_code(400);
            echo json_encode(["status" => 400, "error" => "Bad Request", "message" => "Endpoint invalid or not found."]);
            exit();
        }
        break;
}

// need to pass mysqli along because the include is a different dir up
function getAllNotes($mysqli) {
    $query = "SELECT id, title, note, dt_created, dt_updated
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
    $query = $mysqli->prepare("SELECT id, title, note, dt_created, dt_updated
              from notes
              where id = ?
              order by dt_updated desc");
    $query->bind_param("i", $noteId);
    $query->execute();
    $query_run = $query->get_result();
    $note = mysqli_fetch_assoc($query_run);
    $note['note'] = decrypt_note($note['note']);

    return $note;
}

function getRequestData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $inputData = [];

    if (strpos($contentType, 'application/json') !== false) {
        // read raw input stream for JSON payloads
        $rawInput = file_get_contents('php://input');
        $inputData = json_decode($rawInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {    // if json_decode was successful
            http_response_code(400);
            echo json_encode(["status" => 400, "error" => "Invalid JSON payload"]);
            exit();
        }
    } else {
        // fallback for standard form data or URL-encoded requests
        $inputData = $_POST;
    }

    return $inputData;
}
?>
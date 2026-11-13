<?php

include '../dbutil.php';

// Get the request method
$method = $_SERVER['REQUEST_METHOD'];

// Get the requested endpoint
$endpoint = $_SERVER['PATH_INFO'] ?? '';

// Set the response content type
header('Content-Type: application/json');

// Process the request
switch ($method) {
    case 'GET':
        if ($endpoint === '/notes') {
            $notes = getAllNotes($mysqli);

            http_response_code(200);
            echo json_encode(["status" => 200, "message" => null, "data" => $notes]);

        } elseif (preg_match('/^\/notes\/(\d+)$/', $endpoint, $matches)) {
            $noteId = $matches[1];
            $note = getNoteById((int)$noteId, $mysqli);

            if ($note === null) {
                http_response_code(404);
                echo json_encode(["status" => 404, "error" => "Not Found", "message" => "Note not found."]);
                exit();
            }

            http_response_code(200);
            echo json_encode(["status" => 200, "message" => null, "data" => $note]);
        } else {
            http_response_code(400);
            echo json_encode(["status" => 400, "error" => "Bad Request", "message" => "Endpoint invalid or not found."]);
            exit();
        }
        break;
    case 'POST':
        if ($endpoint === '/notes') {

            $inputData = getRequestData();

            [$title, $note] = validateInputData($inputData);

            $eNote = encrypt_note($note);

            // prepare is better for sql injection than inline string concats
            $query = $mysqli->prepare("INSERT INTO notes (title, note, dt_created, dt_updated)
                                       VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $query->bind_param("ss", $title, $eNote);
            $query_run = $query->execute();

            if ($query_run) {
                // grab the auto-generated id for this connection
                $newNoteId = $mysqli->insert_id;
                
                $newNote = getNoteById((int)$newNoteId, $mysqli);

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
            $existingNote = getNoteById((int)$noteId, $mysqli);

            if ($existingNote === null) {
                http_response_code(404);
                echo json_encode(["status" => 404, "error" => "Not Found", "message" => "Note not found."]);
                exit();
            }

            $inputData = getRequestData();
            
            [$title, $note] = validateInputData($inputData);

            $eNote = encrypt_note($note);

            $query = $mysqli->prepare("UPDATE notes SET title=?, note=?, dt_updated = CURRENT_TIMESTAMP WHERE id=?");
            $query->bind_param("ssi", $title, $eNote, $noteId);
            $query_run = $query->execute();

            if ($query_run) {
                $newNote = getNoteById((int)$noteId, $mysqli);

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
    default:
        // handle unsupported methods (like DELETE, PATCH, OPTIONS)
        header('Allow: GET, POST, PUT');
        http_response_code(405);
        echo json_encode(["status" => 405, "error" => "Method Not Allowed", "message" => "HTTP method '$method' is not supported by this API."]);
        exit();
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
        // db dates are stored UTC, so display the same in responses
        $row['dt_created'] = formatIso8601($row['dt_created']);
        $row['dt_updated'] = formatIso8601($row['dt_updated']);

        $notes[] = $row;
    }
    return $notes;
}

function getNoteById($noteId, $mysqli) {
    $query = $mysqli->prepare("SELECT id, title, note, dt_created, dt_updated
              from notes
              where id = ?");
    $query->bind_param("i", $noteId);
    $query->execute();
    $query_run = $query->get_result();
    $note = mysqli_fetch_assoc($query_run);

    // make sure note exists, otherwise decrypt_note below will throw fatal error
    if (!$note || empty($note['note'])) {
        return null;
    }

    $note['note'] = decrypt_note($note['note']);
    // db dates are stored UTC, so display the same in responses
    $note['dt_created'] = formatIso8601($note['dt_created']);
    $note['dt_updated'] = formatIso8601($note['dt_updated']);

    return $note;
}

function getRequestData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $inputData = [];

    // read raw input stream for JSON payloads
    if (strpos($contentType, 'application/json') !== false) {
        $rawInput = file_get_contents('php://input');
        $inputData = json_decode($rawInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {    // if json_decode was successful
            http_response_code(400);
            echo json_encode(["status" => 400, "error" => "Invalid JSON payload", "message" => "The requst could not be parsed."]);
            exit();
        }
    } else {
        // fallback for POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $inputData = $_POST;
        } else {
            // fallback for PUT requests with form-encoded data
            parse_str(file_get_contents('php://input'), $inputData);
        }
    }

    return $inputData;
}

function validateInputData($inputData) {
    // title is nullable/optional, so make it a proper null for db usage
    $title = trim((string)($inputData['title'] ?? ''));
    $title = ($title === '') ? null : $title;

    $note = $inputData['note'] ?? null;

    // title is nullable, note content is not
    if ($note === null || trim($note) === '') {
        http_response_code(422);
        echo json_encode(["status" => 422,"error" => "Validation Error","message" => "The note content is required and cannot be empty."]);
        exit();
    }

    return [$title, $note];
}

// db dates are stored UTC, so display the same in responses
function formatIso8601($datetimeStr) {
    if (empty($datetimeStr)) {
        return null;
    }

    $dt = new DateTime($datetimeStr, new DateTimeZone('UTC'));
    return $dt->format('Y-m-d\TH:i:s\Z');
}
?>
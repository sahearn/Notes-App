<?php

session_start();

// if no valid session, fail
if (!$_SESSION['notesauthed']) {
    http_response_code(403);
    exit;
}

// CSRF protection; for any state-changing POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || 
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['status' => 403, 'message' => 'Invalid request']);
        exit;
    }
}

require 'dbutil.php';

// add/insert note
if(isset($_POST['save_note']))
{
    // no need for escape string work here - prepared statements handle clean input
    $note  = $_POST['add_note'];
    $title = !empty($_POST['add_title']) ? $_POST['add_title'] : null;

    if($note == NULL)
    {
        $res = [
            'status' => 422,
            'message' => 'Check mandatory fields'
        ];
        echo json_encode($res);
        return;
    }

    $eNote = encrypt_note($note);

    // prepare is better for sql injection than inline string concats
    $query = $mysqli->prepare("INSERT INTO notes (title, note, dt_created, dt_updated)
                               VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
    $query->bind_param("ss", $title, $eNote);
    $query_run = $query->execute();

    if($query_run)
    {
        $res = [
            'status' => 200,
            'message' => 'Note Created Successfully'
        ];
        echo json_encode($res);
        return;
    }
    else
    {
        $res = [
            'status' => 500,
            'message' => 'Note Not Created'
        ];
        echo json_encode($res);
        return;
    }
}

// viewing one note (usually prior to edit)
if(isset($_GET['note_id']))
{
    $note_id = $_GET['note_id'];

    // prepare is better for sql injection than inline string concats
    $query = $mysqli->prepare("select id, title, note
                               from notes
                               where id=?");
    $query->bind_param("i", $note_id);
    $query->execute();
    $query_run = $query->get_result();

    if(mysqli_num_rows($query_run) == 1)
    {
        $note = mysqli_fetch_array($query_run);
        $note['note'] = decrypt_note($note['note']);

        $res = [
            'status' => 200,
            'message' => 'Note Fetch Successfully by id',
            'data' => $note
        ];
        echo json_encode($res);
        return;
    }
    else
    {
        $res = [
            'status' => 404,
            'message' => 'Note Id Not Found'
        ];
        echo json_encode($res);
        return;
    }
}

// update action
if(isset($_POST['update_note']))
{
    $note_id = $_POST['note_id'];
    $note    = $_POST['edit_note'];
    $title   = !empty($_POST['edit_title']) ? $_POST['edit_title'] : null;

    if($note == NULL)
    {
        $res = [
            'status' => 422,
            'message' => 'Check mandatory fields'
        ];
        echo json_encode($res);
        return;
    }

    $eNote = encrypt_note($note);

    // prepare is better for sql injection than inline string concats
    $query = $mysqli->prepare("UPDATE notes SET title=?, note=?, dt_updated = CURRENT_TIMESTAMP WHERE id=?");
    $query->bind_param("ssi", $title, $eNote, $note_id);
    $query_run = $query->execute();

    if($query_run)
    {
        $res = [
            'status' => 200,
            'message' => 'Note Updated Successfully'
        ];
        echo json_encode($res);
        return;
    }
    else
    {
        $res = [
            'status' => 500,
            'message' => 'Note Not Updated'
        ];
        echo json_encode($res);
        return;
    }
}

// delete action
if(isset($_POST['delete_note']))
{
    $note_id = $_POST['note_id'];

    // prepare is better for sql injection than inline string concats
    $query = $mysqli->prepare("DELETE FROM notes WHERE id=?");
    $query->bind_param("i", $note_id);
    $query_run = $query->execute();

    if($query_run)
    {
        $res = [
            'status' => 200,
            'message' => 'Note Deleted Successfully'
        ];
        echo json_encode($res);
        return;
    }
    else
    {
        $res = [
            'status' => 500,
            'message' => 'Note Not Deleted'
        ];
        echo json_encode($res);
        return;
    }
}

?>
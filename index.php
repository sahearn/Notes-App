<?php

session_start();

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// if valid session, continue
if (isset($_POST['frm_auth'])) {
    if (password_verify($_POST['frm_auth'],'**hashed password**')) {
        $_SESSION['notesauthed'] = true;
        header('Location: /notes/'); // or your notes location
    }
}

// if no valid session, prompt for pw
if (!$_SESSION['notesauthed']) {
    echo '<form action="" method="post">';
    echo '<input type="password" name="frm_auth"> <input type="submit" value="authenticate">';
    echo '</form>';
    exit;
}

require 'dbutil.php';

// markdown support
include 'parsedown-1.8.0/Parsedown.php';
$Parsedown = new Parsedown();

// db times are UTC - make them local
function formatLocalTime(string $utcTime, string $tz = 'America/New_York', string $format = 'd-M-Y g:ia'): string {
    $dt = new DateTime($utcTime, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone($tz));
    return $dt->format($format);
}

// custom render markdown task lists to checkboxes
function parseMarkdownWithTasks($text) {
    $parsedown = new Parsedown();
    $html = $parsedown->text($text);

    // Replace checked boxes
    $html = preg_replace('/<li>\s*\[x\]\s+/i', '<li class="notelistitems"><input type="checkbox" checked disabled> ', $html);
    
    // Replace unchecked boxes
    $html = preg_replace('/<li>\s*\[ \]\s+/', '<li class="notelistitems"><input type="checkbox" disabled> ', $html);

    return $html;
}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">

    <title>Notes</title>

    <link rel="stylesheet" href="//cdn.jsdelivr.net/npm/alertifyjs@1.14.0/build/css/alertify.min.css"> <? // https://alertifyjs.com/guide.html ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"> <? // https://cdnjs.com/libraries/font-awesome ?>

    <style>
    table {
        width: 100%;
    }
    table, th, td {
        border: 1px solid #aaa;
        border-collapse: collapse;
        font-size: 0.9em;
        padding: 1px 3px;
    }
    .btn-sm {
        padding: 2px 4px;
    }

    #contentpane {
        border-left : 1px solid #ccc;
    }
    @media only screen and (max-width: 800px) {
        #contentpane {
            border-left : 0;
            border-top : 1px solid #ccc;
            padding-top : 2em;
        }
    }
    @media only screen and (min-width: 801px) and (max-width: 1200px) {
        #contentpane {
        }
    }
    a.noteEntries {
        color : #0d6efd;
        text-decoration: none;
    }
    a.noteEntries:hover {
        text-decoration: underline;
    }
    .noteDate {
        color: #888;
    }
    .noteDates {
        color: #666;
        font-size: 0.8em;
    }
    .editNoteBtn {
        text-decoration: none;
    }
    .deleteNoteBtn {
        color : #b00;
        text-decoration: none;
    }

    /* used for data-target displays in nav and content panes */
    .values { display: none; }
    .active { font-weight: bold; }

    /* used to style markdown checkboxes */
    .notelistitems { list-style-type: none; }
    ul:has(> .notelistitems) { padding: 0; }
    </style>

</head>
<body>

<!-- Add -->
<div class="modal fade" id="noteAddModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="addModalLabel">Add Note</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="saveNote">
            <div class="modal-body">

                <div id="errorMessage" class="alert alert-warning d-none"></div>

                <div class="mb-3">
                    <label for="add_title">Title</label> (optional)
                    <input type="text" name="add_title" id="add_title" class="form-control">
                </div>
                <div class="mb-3">
                    <label for="add_note">Note</label>
                    <textarea name="add_note" id="add_note" class="form-control" rows="8"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Save Note</button>
            </div>
        </form>
        </div>
    </div>
</div>

<!-- Edit -->
<div class="modal fade" id="noteEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="editModalLabel">Edit Note</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="updateNote">
            <div class="modal-body">

                <div id="errorMessageUpdate" class="alert alert-warning d-none"></div>

                <input type="hidden" name="note_id" id="note_id" >

                <div class="mb-3">
                    <label for="edit_title">Title</label> (optional)
                    <input type="text" name="edit_title" id="edit_title" class="form-control">
                </div>
                <div class="mb-3">
                    <label for="edit_note">Note</label>
                    <textarea name="edit_note" id="edit_note" class="form-control" rows="8"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Update Note</button>
            </div>
        </form>
        </div>
    </div>
</div>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <? // override font size because bootstrap wants to make this huge, but starting with H4 doesn't validate with w3c ?>
                    <h1 style="font-size:1.5rem;">Notes
                        
                        <button type="button" class="btn btn-primary float-end" data-bs-toggle="modal" data-bs-target="#noteAddModal">
                            Add Note
                        </button>
                    </h1>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-4">
                            <?php
                            $query = "select id, title, note, dt_created, dt_updated
                                      from notes
                                      order by dt_updated desc";
                            $query_run = mysqli_query($mysqli, $query);

                            if(mysqli_num_rows($query_run) > 0)
                            {
                                foreach($query_run as $content)
                                {
                                    ?>
                                    <div class="row ms-1 mb-2">
                                        <span class="noteDate ps-0"><?= formatLocalTime($content['dt_updated']) ?></span>
                                        <br>
                                        <a class="noteEntries" href="#" data-target="value-<?= $content['id'] ?>">
                                        <?php
                                        if (isset($content['title']) && $content['title'] !== '') {
                                            print htmlspecialchars(substr($content['title'], 0, 35), ENT_QUOTES, 'UTF-8');
                                        } else {
                                            print htmlspecialchars(substr(decrypt_note($content['note']), 0, 35), ENT_QUOTES, 'UTF-8') . '...';
                                        }
                                        ?>
                                        </a>
                                    </div>
                                    <?php
                                    // decrypt the note, remove bad html, render any markdown
                                    $renderText = $Parsedown->line(htmlspecialchars(decrypt_note($content['note']), ENT_QUOTES, 'UTF-8'));

                                    // custom function to render task lists (checkboxes)
                                    $renderText = parseMarkdownWithTasks($renderText);
                                    ?>
                                    <div class="values" id="value-<?= $content['id'] ?>"><?= $renderText ?><br><br><a href="#" title="edit" class="editNoteBtn fa-regular fa-pen-to-square" data-value="<?= $content['id'] ?>"></a> &nbsp; <a href="#" title="delete" class="deleteNoteBtn fa-regular fa-trash-can" data-value="<?= $content['id'] ?>"></a> &nbsp; <span class="noteDates">created: <?= formatLocalTime($content['dt_created']) ?> &nbsp; updated: <?= formatLocalTime($content['dt_updated']) ?></span></div>
                                    <?php
                                }
                            }
                            mysqli_free_result($query_run);
                            ?>
                        </div>
                        <div class="col-md-9 ps-4 pb-3" id="contentpane"></div>
                    </div>
                </div>
                <div class="card-footer">
                <a href="/" style="text-decoration:none;">&#0171; Back to main site</a>
                </div>
            </div>
        </div>
    </div>
</div>
<br>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="//cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>

<script>
// data-target nav/content display
document.querySelectorAll('[data-target]').forEach(link => {
  link.addEventListener('click', e => {
    e.preventDefault();
    document.querySelectorAll('[data-target]').forEach(l => l.classList.remove('active'));
    link.classList.add('active');
    const value = document.getElementById(link.dataset.target);
    document.getElementById('contentpane').innerHTML = value.innerHTML;
  });
});

// CSRF protection, passed with forms
const csrfToken = $('meta[name="csrf-token"]').attr('content');

// add new
$(document).on('submit', '#saveNote', function (e) {
    e.preventDefault();

    var formData = new FormData(this);
    formData.append("save_note", true);
    formData.append("csrf_token", csrfToken);

    $.ajax({
        type: "POST",
        url: "code.php",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            
            var res = jQuery.parseJSON(response);
            if(res.status == 422) {
                $('#errorMessage').removeClass('d-none');
                $('#errorMessage').text(res.message);

            }else if(res.status == 200){

                $('#errorMessage').addClass('d-none');
                $('#noteAddModal').modal('hide');
                $('#saveNote')[0].reset();

                alertify.set('notifier','position', 'top-right');
                alertify.success(res.message);

                setTimeout(function() {
                    location.reload();
                }, 1500);

            }else if(res.status == 500) {
                alert(res.message);
            }
        }
    });

});

// edit window
$(document).on('click', '.editNoteBtn', function () {

    var note_id = $(this).data('value');
    
    $.ajax({
        type: "GET",
        url: "code.php?note_id=" + note_id,
        cache: false,
        success: function (response) {

            var res = jQuery.parseJSON(response);
            if(res.status == 404) {

                alert(res.message);
            }else if(res.status == 200){

                $('#note_id').val(res.data.id);
                $('#edit_title').val(res.data.title);
                $('#edit_note').val(res.data.note);

                $('#noteEditModal').modal('show');
            }

        }
    });

});

// edit action
$(document).on('submit', '#updateNote', function (e) {
    e.preventDefault();

    var formData = new FormData(this);
    formData.append("update_note", true);
    formData.append("csrf_token", csrfToken);

    $.ajax({
        type: "POST",
        url: "code.php",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            
            var res = jQuery.parseJSON(response);
            if(res.status == 422) {
                $('#errorMessageUpdate').removeClass('d-none');
                $('#errorMessageUpdate').text(res.message);

            }else if(res.status == 200){

                $('#errorMessageUpdate').addClass('d-none');

                alertify.set('notifier','position', 'top-right');
                alertify.success(res.message);
                
                $('#noteEditModal').modal('hide');
                $('#updateNote')[0].reset();

                setTimeout(function() {
                    location.reload();
                }, 1500);
            }else if(res.status == 500) {
                alert(res.message);
            }
        }
    });

});

// delete action
$(document).on('click', '.deleteNoteBtn', function (e) {
    e.preventDefault();

    if(confirm('Are you sure you want to delete this note?'))
    {
        var note_id = $(this).data('value');
        $.ajax({
            type: "POST",
            url: "code.php",
            data: {
                'delete_note': true,
                'note_id': note_id,
                'csrf_token': csrfToken
            },
            success: function (response) {

                var res = jQuery.parseJSON(response);
                if(res.status == 500) {

                    alert(res.message);
                }else{
                    alertify.set('notifier','position', 'top-right');
                    alertify.success(res.message);

                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                }
            }
        });
    }
});
</script>

</body>
</html>

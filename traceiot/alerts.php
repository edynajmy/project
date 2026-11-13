<?php
function setSweetAlert($icon, $title, $text) {
    $_SESSION['sweet_alert'] = [
        'icon' => $icon,
        'title' => $title,
        'text' => $text
    ];
}

function showSweetAlert() {
    if (isset($_SESSION['sweet_alert'])) {
        $icon = addslashes($_SESSION['sweet_alert']['icon']);
        $title = addslashes($_SESSION['sweet_alert']['title']);
        $text = addslashes($_SESSION['sweet_alert']['text']);

        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <script>
            Swal.fire({
                icon: '$icon',
                title: '$title',
                text: '$text',
                confirmButtonText: 'OK'
            });
        </script>
        ";

        unset($_SESSION['sweet_alert']);
    }
}
?>
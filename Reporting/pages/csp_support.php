<?php

header("Content-Type: text/javascript"); ?>


jQuery(function () {
    jQuery("#reporting").change(function () {
        location.href = jQuery(this).val();
    })
})

<?php
if ( isset( $_GET['r'] ) and !empty( $_GET['r'] ) ) {
    $current_page = strip_tags( $_GET['r'] );
} else { exit; }

if ( $current_page == 'sythdet' ) { // issues by project
    if ( isset( $_SESSION['synthese_detail_js'] ) and !empty( $_SESSION['synthese_detail_js'] ) ) {
        echo $_SESSION['synthese_detail_js'];
    }
} elseif ( $current_page == 'tmgo' ) { // issues by severity
    if ( isset( $_SESSION['test_mgo_js'] ) and !empty( $_SESSION['test_mgo_js'] ) ) {
        echo $_SESSION['test_mgo_js'];
    }
}
?>

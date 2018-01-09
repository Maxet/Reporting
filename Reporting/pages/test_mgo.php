<?php
require_once 'common_includes.php';
require_api( 'html_api.php' );
require_api( 'print_api.php' );
layout_page_header();
layout_page_begin( 'plugin.php?page=Reporting/start_page' );

$project_id                 = helper_get_current_project();
$specific_where             = helper_project_specific_where( $project_id );
$mantis_bug_table           = db_get_table( 'bug' );
$resolved_status_threshold  = config_get( 'bug_resolved_status_threshold' );
$status_enum_string         = lang_get( 'status_enum_string' );
$status_values              = MantisEnum::getValues( $status_enum_string );
$severity_enum_string       = lang_get( 'severity_enum_string' );
$count_states               = count_states();


// start and finish dates and times
$db_datetimes = array();

$db_datetimes['start']  = strtotime( cleanDates( 'date-from', $dateFrom ) . " 00:00:00" );
$db_datetimes['finish'] = strtotime( cleanDates( 'date-to', $dateTo ) . " 23:59:59" );


// get data
$issues_fetch_from_db = array();
function affiche_mon_tableau () {
	$project_id                 = helper_get_current_project();
	$specific_where             = helper_project_specific_where( $project_id );
	if ($project_id == '0'){
		$project_id ='*';
	}
	global $db_datetimes;
db_param_push();

/*************************************************************************************/
/*    DEBUT BLOC SQL                                                                 */
/*************************************************************************************/

//requete pour alimentation des colonnes standard AdeRHis
$query = "
        SELECT bug.id as 'id_fiche'
			 , proj.name as 'application'
			 , bug.summary as 'titre'
			 , user.realname as 'demandeur'
			 , cat.name as 'composant'
			 , bug.project_id as 'project_id'
		  FROM mantis_bug_table bug
		  LEFT JOIN mantis_project_table proj on (proj.id = bug.project_id)
		  LEFT JOIN mantis_user_table user on (user.id = bug.reporter_id)
		  LEFT JOIN mantis_category_table cat on (cat.id = bug.category_id)
		 WHERE bug.".$specific_where."
		   AND bug.id in (select distinct(hist.bug_id) 
							from mantis_bug_history_table hist
						   where hist.date_modified >= " . db_prepare_string( $db_datetimes['start'] ) . "
							 and hist.date_modified <= " . db_prepare_string( $db_datetimes['finish'] ) . ")
		 GROUP BY bug.id
		 ORDER BY bug.id, proj.name, bug.summary, user.realname, cat.name
        ";
// Requete de recuperation des noms des champs specifiques pour creation de l entete du tableau
$query_liste_cs= "
	SELECT REPLACE(name,' ', '_') as 'name'
	  FROM mantis_custom_field_table 
	 WHERE id in (SELECT field_id
					  FROM mantis_custom_field_project_table
					 WHERE ".$specific_where .")
	 ORDER BY id ";



/*************************************************************************************/
/*    FIN BLOC SQL                                                                   */
/*************************************************************************************/
$result = db_query( $query );
$row_count = db_num_rows($result);
	//constuction de l entete du tableau
	$type = "open";
	$data_table_print = "
	<table  class='table table-bordered'>
        <thead>
        <tr class='tblheader'>
            <td class='dt-right nowrap' width='20%'>" . lang_get( 'plugin_Reporting_id_fiche' ) . "</td>
			<td class='dt-right nowrap' width='20%'>" . lang_get( 'plugin_Reporting_Application' ) . "</td>
			<td class='dt-right nowrap' width='20%'>" . lang_get( 'plugin_Reporting_Titre' ) . "</td>
			<td class='dt-right nowrap' width='20%'>" . lang_get( 'plugin_Reporting_Demandeur' ) . "</td>
			<td class='dt-right nowrap' width='20%'>" . lang_get( 'plugin_Reporting_Composant' ) . "</td>";
	//insertion des colonnes specifiques aux projets
	$result_liste_cs = db_query( $query_liste_cs );
	$row_count_liste_cs = db_num_rows($result_liste_cs);
	$t_row_liste_cs = array();
		
	while( $t_row_liste_cs = db_fetch_array($result_liste_cs) ) {
		$t_name_field = $t_row_liste_cs['name'];
		$data_table_print .= "<td class='dt-right nowrap'>" . lang_get( 'plugin_Reporting_'.$t_name_field .'' ) . "</td>";	
	}
	$data_table_print .= "	
		</tr>
		";
		$boucle = 0;
	$t_row = array();
	 while( $t_row = db_fetch_array($result) ) {
		//$sprint = "nombre de ligne : " .$row_count." id : ".$t_row['Application']."|test";
		
		//$boucle = $boucle+1;
		$t_id_fiche = $t_row['id_fiche'];
		$t_Application = $t_row['application'];
		$t_Titre = $t_row['titre'];
		$t_Demandeur = $t_row['demandeur'];
		$t_Composant = $t_row['composant'];
		$t_project_id = $t_row['project_id'];
		
		
		$data_table_print .= "<tr>";
		$data_table_print .= "<td class='dt-right nowrap'>". $t_id_fiche. "</td>";
		$data_table_print .= "<td class='dt-right nowrap'>". $t_Application. "</td>";
		$data_table_print .= "<td class='dt-right nowrap'>". $t_Titre. "</td>";
		$data_table_print .= "<td class='dt-right nowrap'>". $t_Demandeur. "</td>";
		$data_table_print .= "<td class='dt-right nowrap'>". $t_Composant. "</td>";
		
		//alimentation champs specifiques
		
		// Requete pour alimentation des colonnes spécifiques AdeRHis
		$query_value_cs = "	
			SELECT value as 'value', type as 'type'
			  FROM mantis_custom_field_string_table
			  LEFT JOIN mantis_custom_field_table on (id = field_id)
			 WHERE field_id in (SELECT field_id
								  FROM mantis_custom_field_project_table
								 WHERE ".$specific_where .")
			   AND bug_id = ".$t_id_fiche."
			 ORDER BY field_id ";
		
		$result_value_cs = db_query( $query_value_cs );
		$row_count_value_cs = db_num_rows($result_value_cs);
		$t_row_value_cs = array();
		
		while( $t_row_value_cs = db_fetch_array($result_value_cs) ) {
			$t_Value = $t_row_value_cs['value'];
			$t_type = $t_row_value_cs['type'];
			if ($t_type == 8){
				$t_Value = date('Y-m-d',$t_Value);
			}
			$data_table_print .= "<td class='dt-right nowrap'>". $t_Value. "</td>";
		}
			 
		$data_table_print .= "</tr>";
	}
	$data_table_print .= "
			</thead>";
	// build end of the table
    $data_table_print .= "
    </tbody></table>
    ";

    return $data_table_print;
	//return $query_value_cs;
	//return $result;
	//return $sprint;
	//return $project_id;  
	
}

//fonction extraction
function extraction_csv() {
header("Content-type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=nom_fichier.xls");
print $data_table_print;
}
$main_js = <<<EOT
$(document).ready( function () {
    $('#open').DataTable( {
        dom: 'lfrtip<"clear spacer">T',
        "order": [[ $tmp_cnt_op, 'desc' ], [ 0, 'asc' ]],
        "autoWidth": false,
        "scrollX": true,
        "searching": false,
        "lengthChange": false,
        "pageLength": 10,
        "aoColumns": [
            { "asSorting": [ "asc", "desc" ] },
EOT;

$i = 0;
while ( $i <= $count_states['open'] ) {
    $main_js .= <<<EOT
    { "asSorting": [ "desc", "asc" ] },
EOT;
    $i++;
}

$main_js .= <<<EOT
        ],
        $dt_language_snippet
    } );

    $('#resolved').DataTable( {
        dom: 'lfrtip<"clear spacer">T',
        "order": [[ $tmp_cnt_rs, 'desc' ], [ 0, 'asc' ]],
        "autoWidth": false,
        "scrollX": true,
        "searching": false,
        "lengthChange": false,
        "pageLength": 10,
        "aoColumns": [
            { "asSorting": [ "asc", "desc" ] },
EOT;

$i = 0;
while ( $i <= $count_states['resolved'] ) {
    $main_js .= <<<EOT
    { "asSorting": [ "desc", "asc" ] },
EOT;
    $i++;
}


$main_js .= <<<EOT
        ],
        $dt_language_snippet
		} );

    } );
EOT;

$_SESSION['test_mgo_main_js'] = $main_js;
?>

<script type='text/javascript' src="<?php echo plugin_page( 'csp_support&r=sythdet' ); ?>"></script>
<?php
//html_button_bug_change_status( 1 );
$t_config_var_value = config_get( 'status_enum_string', null, null, 1 ); //récupération de tous les statuts

$t_enum_values = MantisEnum::getValues( $t_config_var_value ); // récupération des libellés des statuts

$t_statut_perso = array('20','25','52','60','90'); //tableau des statuts à surveiller (sera à récupérer via la config perso par projets)

foreach ( $t_enum_values as $t_enum_value ) {
	$t_enum_list[$t_enum_value] = get_enum_element( 'status', $t_enum_value );// contruction du tableau avec l'id du statut et son libellé
}
// affichage des statuts dans un tableau
/*
echo"<div><table class='table table-bordered'><tr><td>ID</td><td>Statut</td></tr>";
foreach( $t_enum_list as $t_key => $t_val) {
		echo '<tr><td>' . $t_key . '</td><td>'. $t_val .'</td></tr>';
	}
echo '</table></div>';
*/
//affichage en fonction du tableau perso 				
echo"<div><table class='table table-bordered'><thead><tr class='tblheader'><td class='dt-right nowrap'>ID</td>";
foreach ($t_statut_perso as $statut){
	foreach( $t_enum_list as $t_key => $t_val) {
		if ($statut == $t_key){
		echo "<td class='dt-right nowrap'>". $t_val ."</td>";
		}
	}	
}
echo '</tr></thead>';
//alimentation du tableau avec les dates de changement par bug_id
//step 1 : obtenir la liste des bug
$query_liste_bug = "
	SELECT id as 'bug_id'
	  FROM mantis_bug_table
	  order by id
	";
$result_liste_bug = db_query( $query_liste_bug );
$t_row_liste_bug = array();
while( $t_row_liste_bug = db_fetch_array($result_liste_bug) ) {
	$t_bug_id = $t_row_liste_bug['bug_id'];
	//echo "</br>bug :". $t_bug_id ." ; ";
	echo "<tr><td class='dt-right nowrap'>". $t_bug_id ."</td>";
	//step 2 : chercher les dates de changements en fonction de la liste des statuts
	foreach ($t_statut_perso as $statut){
	//echo "STATUT : ". $statut ." ; ";
		$query_date_changed = "
			SELECT date_modified as 'date'
			  FROM mantis_bug_history_table
			 WHERE field_name = 'status'
			   AND bug_id = ". $t_bug_id ."
			   AND new_value = ". $statut ."
			";
		$result_date_changed = db_query( $query_date_changed );
		$row_count = db_num_rows($result_date_changed);
		//echo " nbresultat : ". $row_count ." ; ";
		if ($row_count >0){
			$t_row_date_changed = array();
			while( $t_row_date_changed = db_fetch_array($result_date_changed) ) {
				$t_bug_date = date('Y-m-d',$t_row_date_changed['date']);
				//echo "bug_date : ". $t_bug_date ." !	";
				//echo $query_date_changed;
				echo "<td class='dt-right nowrap'>". $t_bug_date ."</td>";
			}//fin while $t_row_date_changed
		}
		else
		{
			echo "<td class='dt-right nowrap'></td>";
		}
	}//fin foreach $t_statut_perso
	echo "</tr>";
}//fin while $t_row_liste_bug


echo '</table></div>';


?>
<?php
echo"<div>
	<p> Tableau type SLA </p>
	<table class='table table-bordered'>";
echo"<thead><tr class='tblheader'>";
echo"<td rowspan = 2 colspan = 2></td>
	 <td colspan = 7 >Gravité</td></tr><tr>";
echo"<td class='dt-right nowrap'> Demande Assitance </td>
	 <td class='dt-right nowrap'> Evolution</td>
	 <td class='dt-right nowrap'> Ano.mineur </td>
	 <td class='dt-right nowrap'> Ano.majeur </td>
	 <td class='dt-right nowrap'> Ano.critique </td>
	 <td class='dt-right nowrap'> Ano.bloquante </td>
	 </tr></thead>";
echo"<tr><td rowspan = 7 >priorite</td>";
$query_sla="
	SELECT
	   priorite,
	   SUM(IF(gravite = 'Demande Assitance', valeur, 0)) AS da,
	   SUM(IF(gravite = 'Evolution', valeur, 0)) AS evo,
	   SUM(IF(gravite = 'Ano.mineur', valeur, 0)) AS a_mi,
	   SUM(IF(gravite = 'Ano.majeur', valeur, 0)) AS a_ma,
	   SUM(IF(gravite = 'Ano.critique', valeur, 0)) AS a_cr,
	   SUM(IF(gravite = 'Ano.bloquante', valeur, 0)) AS a_bl
	FROM test_mgo
	WHERE project_id = 1
	GROUP BY priorite
	ORDER BY priorite
";
$result_sla = db_query( $query_sla );
$row_count_sla = db_num_rows($result_sla);
while( $t_row_sla = db_fetch_array($result_sla) ) {
	$t_sla_prio	= $t_row_sla['priorite'];
	$t_sla_da	= $t_row_sla['da'];
	$t_sla_evo	= $t_row_sla['evo'];
	$t_sla_a_mi	= $t_row_sla['a_mi'];
	$t_sla_a_ma	= $t_row_sla['a_ma'];
	$t_sla_a_cr	= $t_row_sla['a_cr'];
	$t_sla_a_bl	= $t_row_sla['a_bl'];
	
	echo"<tr>";
	echo"<td class='dt-right nowrap'>".$t_sla_prio."</td>
		 <td class='dt-right nowrap'>".$t_sla_da."</td>
		 <td class='dt-right nowrap'>".$t_sla_evo."</td>
		 <td class='dt-right nowrap'>".$t_sla_a_mi."</td>
		 <td class='dt-right nowrap'>".$t_sla_a_ma."</td>
		 <td class='dt-right nowrap'>".$t_sla_a_cr."</td>
		 <td class='dt-right nowrap'>".$t_sla_a_bl."</td>
	";
	echo"</tr>";
}//fin while $t_row_date_changed



echo"</table></div>";
?>


<?php layout_page_end();

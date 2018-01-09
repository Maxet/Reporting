<?php
	require_once 'common_includes.php';

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
			 ORDER BY id 
		";


		/*************************************************************************************/
		/*    FIN BLOC SQL                                                                   */
		/*************************************************************************************/
		$result = db_query( $query );
		$row_count = db_num_rows($result);
		//constuction de l entete du tableau
		$data_table_print = "
			<table class='table table-bordered'>
				<thead>
				<tr class='tblheader'>
					<td class='dt-right nowrap' width='20%'>" . lang_get( 'plugin_Reporting_id_fiche' ) . "</td>
					<td class='dt-right nowrap' width='20%'>" . lang_get( 'plugin_Reporting_Application' ) . "</td>
					<td class='dt-right nowrap' width='20%'>" . lang_get( 'plugin_Reporting_Titre' ) . "</td>
					<td class='dt-right nowrap' width='20%'>" . lang_get( 'plugin_Reporting_Demandeur' ) . "</td>
					<td class='dt-right nowrap' width='20%'>" . lang_get( 'plugin_Reporting_Composant' ) . "</td>
		";
		//insertion des colonnes specifiques aux projets
		$result_liste_cs = db_query( $query_liste_cs );
		$row_count_liste_cs = db_num_rows($result_liste_cs);
		$t_row_liste_cs = array();
			
		while( $t_row_liste_cs = db_fetch_array($result_liste_cs) ) {
			$t_name_field = $t_row_liste_cs['name'];
			$data_table_print .= "<td class='dt-right nowrap'>" . lang_get( 'plugin_Reporting_'.$t_name_field .'' ) . "</td>";	
		}
		$data_table_print .= "	
			</tr></thead><tbody>
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
				 ORDER BY field_id 
			";
			
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
		// build end of the table
		$data_table_print .= "
			</tbody></table>
		";

		return $data_table_print;
 		
	}// fin fonction affiche_mon_tableau

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

	$_SESSION['synthese_detail_main_js'] = $main_js;

?>

<script type='text/javascript' src="<?php echo plugin_page( 'csp_support&r=sythdet' ); ?>"></script>

<div id="wrapper">
	<?php echo $whichReport; ?>
	<p class='space20Before' />
	
	<div id="titleText">
		<div id="scope">
			<?php echo lang_get( 'plugin_Reporting_project' ); ?>: <?php echo project_get_name( $project_id ); ?>
		</div>
		<div id="sup">
			<?php if ( $project_id == ALL_PROJECTS ) { echo "<sup>&dagger;</sup>"; } ?>
		</div>
	</div>
	
	<p class="clear" />

	<div id="filter">
		<strong><?php echo lang_get( 'plugin_Reporting_timeframe' ); ?></strong>
		<form method="get">
			<input type="hidden" name="page" value="Reporting/synthese_detail" />
			<?php echo form_security_field( 'date_picker' ) ?>

			<div>
				<div>
					<input type="text" id="from" name="date-from" class="datetimepicker input-sm"
						data-picker-locale="<?php echo lang_get_current_datetime_locale() ?>"
						data-picker-format="Y-MM-DD"
						size="12" value="<?php echo cleanDates('date-from', $dateFrom); ?>" />
					<i class="fa fa-calendar fa-xlg datetimepicker"></i>
					<span class="widen20">-</span>
					<input type="text" id="to" name="date-to" class="datetimepicker input-sm"
						data-picker-locale="<?php echo lang_get_current_datetime_locale() ?>"
						data-picker-format="Y-MM-DD"
						size="12" value="<?php echo cleanDates('date-to', $dateTo); ?>" />
					<i class="fa fa-calendar fa-xlg datetimepicker"></i>
				</div>
			</div>
			<div>
				<span class="widen10">&nbsp;</span><input type="submit" id="displaysubmit" value=<?php echo lang_get( 'plugin_Reporting_display' ); ?> class="button" />
			</div>
		</form>
	</div>

	<p class="space40Before" />
		<strong>&raquo; <?php echo lang_get( 'plugin_Reporting_result' ); ?></strong>
	<div class="widget-body">
		<div class="widget-toolbox padding-8 clearfix">
			<div class="btn-toolbar">
				<div class="btn-group pull-left">
					<?php
						# -- Print and Export links --
						print_small_button( 'plugins/Reporting/pages/extract_csv.php?start='.$db_datetimes['start'].'&finish='.$db_datetimes['finish'].'', lang_get( 'csv_export' ) );
						print_small_button( 'plugins/Reporting/pages/extract_excel_xml.php?start='.$db_datetimes['start'].'&finish='.$db_datetimes['finish'].'', lang_get( 'excel_export' ) ); 
					?>
				</div>
				<div class="btn-group pull-right"><?php
					# -- Page number links --
					$f_filter	= gpc_get_int( 'filter', 0);
					print_page_links( 'view_all_bug_page.php', 1, $t_page_count, (int)$f_page_number, $f_filter );
					?>
				</div>
			</div>
		</div>
		<?php echo affiche_mon_tableau(); ?>
	</div>
	<p class="space40Before" />

	<?php if ( $project_id == ALL_PROJECTS ) { echo "<p />&dagger; " . lang_get( 'plugin_Reporting_priv_proj_skip' ) . "<br />"; } ?>

	<?php if ( $showRuntime == 1 ) { printf( "<p class='graycolor'>" . lang_get( 'plugin_Reporting_runtime_string' ) . "</p>", round(microtime(true) - $starttime, 5) ); } ?>

</div>

<?php layout_page_end(); ?>

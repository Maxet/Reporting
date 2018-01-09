<?php
	require_api( 'authentication_api.php' );
	require_api( 'bug_api.php' );
	require_api( 'category_api.php' );
	require_api( 'config_api.php' );
	require_api( 'constant_inc.php' );
	require_api( 'file_api.php' );
	require_api( 'helper_api.php' );
	require_api( 'project_api.php' );
	require_api( 'user_api.php' );
	require_api( 'excel_api.php');
		
	function report_get_columns($specific_where) {
		$t_columns = array(''. lang_get( 'plugin_Reporting_id_fiche' ).''
						  ,''.lang_get( 'plugin_Reporting_Application' ).''
						  ,''.lang_get( 'plugin_Reporting_Titre' ).''
						  ,''.lang_get( 'plugin_Reporting_Demandeur' ).''
						  ,''.lang_get( 'plugin_Reporting_Composant' ).'');
		
		$query_liste_cs= "
			SELECT REPLACE(name,' ', '_') as 'name'
			  FROM mantis_custom_field_table 
			 WHERE id in (SELECT field_id
							FROM mantis_custom_field_project_table
						   WHERE ".$specific_where .")
			 ORDER BY id ";
		 
		$result_liste_cs = db_query( $query_liste_cs );
		$t_row_liste_cs = array();
			
		while( $t_row_liste_cs = db_fetch_array($result_liste_cs) ) {
			$t_name_field = $t_row_liste_cs['name'];
			$t_columns[] = ''.lang_get( 'plugin_Reporting_'.$t_name_field .'' ).'';	
		}		
		return $t_columns;
	}

	function report_get_nb_line($specific_where,$start_date,$finish_date){
		$query_nb_line = "
			SELECT bug.id
			  FROM mantis_bug_table bug
			  LEFT JOIN mantis_project_table proj on (proj.id = bug.project_id)
			  LEFT JOIN mantis_user_table user on (user.id = bug.reporter_id)
			  LEFT JOIN mantis_category_table cat on (cat.id = bug.category_id)
			 WHERE bug.".$specific_where."
			   AND bug.id in (select distinct(hist.bug_id) 
								from mantis_bug_history_table hist
							   where hist.date_modified >= " . $start_date . "
								 and hist.date_modified <= " . $finish_date . ")
			 ";
		$result_nb_line = db_query( $query_nb_line );
		$row_count_nb_line = db_num_rows($result_nb_line);
		
		return $row_count_nb_line;	
	}

	function report_get_content($specific_where,$start_date,$finish_date){
		$t_content = array();
		//requete pour alimentation des colonnes standard AdeRHis
		$query_content_standard = " 
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
							   where hist.date_modified >= " . db_prepare_string( $start_date ). "
								 and hist.date_modified <= " . db_prepare_string( $finish_date ) . ")
			 GROUP BY bug.id
			 ORDER BY bug.id, proj.name, bug.summary, user.realname, cat.name
			";
		//Debut traitement
		$result_content_standard = db_query( $query_content_standard );		
				 
		$t_row = array();
		$boucle = 0;
		while( $t_row = db_fetch_array($result_content_standard) ) {
			
			$t_id_fiche = $t_row['id_fiche'];
			$t_content[$boucle][] = $t_row['id_fiche'];
			$t_content[$boucle][] = $t_row['application'];
			$t_content[$boucle][] = $t_row['titre'];
			$t_content[$boucle][] = $t_row['demandeur'];
			$t_content[$boucle][] = $t_row['composant'];
			
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
				$t_content[$boucle][] = $t_Value;
			}
			$boucle = $boucle + 1;
		}
		return $t_content;
	}

	function report_excel_get_columns($specific_where){
		$t_entetes = array(''. lang_get( 'plugin_Reporting_id_fiche' ).''
						  ,''.lang_get( 'plugin_Reporting_Application' ).''
						  ,''.lang_get( 'plugin_Reporting_Titre' ).''
						  ,''.lang_get( 'plugin_Reporting_Demandeur' ).''
						  ,''.lang_get( 'plugin_Reporting_Composant' ).'');
		
		$query_liste_cs= "
			SELECT REPLACE(name,' ', '_') as 'name'
			  FROM mantis_custom_field_table 
			 WHERE id in (SELECT field_id
							FROM mantis_custom_field_project_table
						   WHERE ".$specific_where .")
			 ORDER BY id ";
		 
		$result_liste_cs = db_query( $query_liste_cs );
		$t_row_liste_cs = array();
			
		while( $t_row_liste_cs = db_fetch_array($result_liste_cs) ) {
			$t_name_field = $t_row_liste_cs['name'];
			$t_entetes[] = ''.lang_get( 'plugin_Reporting_'.$t_name_field .'' ).'';	
		}
		
		$report_excel_columns = excel_get_start_row( $p_style_id );
		foreach( $t_entetes as $t_entete ) {
			$report_excel_columns .= excel_format_column_title( column_get_title( $t_entete ) );
		}
		$report_excel_columns .= '</Row>';
		
		return $report_excel_columns;
	}
?>
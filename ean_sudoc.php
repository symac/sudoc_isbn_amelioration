<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" lang="fr">
<head>
	<title>Geobib.fr - Exemplarisation Sudoc EAN</title>
	<meta http-equiv="Content-Type" content="text/html;charset=iso-8859-1" />
	<link rel="stylesheet" href="/css/style2.css" type="text/css" />
	<style type="text/css">
	p {
		margin-bottom:10px;
		text-align:justify;
	}
	</style>
	<script type="text/javascript" src="/js/jquery-1.7.1.min.js"></script>
	<script type="text/javascript" src="https://www.google.com/jsapi"></script>
</head>
<body>
	<div id="global">
		<?php
			include("../menu.php");
		?>
		<div id="contenu" style='padding-top:30px; width:750px; margin:auto;'>
			<h1>Contexte</h1>
			<p>
				Dans le cadre de l'informatisation dans le sudoc d'un fonds d'ouvrages r�cents (poss�dant des ISBN) pour lequel on ne poss�de pas d'inventaire d�taill� des isbn, il peut �tre plus simple de partir d'une liste facile � constituer, celle des EAN des ouvrages (code barre commercial). Cette liste a le m�rite de pouvoir �tre constitu�e en "bippant" les codes barres commerciaux et peut se faire rapidement.
			</p>
			<h1>Principe</h1>
			<p>
				L'ABES propose un <a href='http://carmin.sudoc.abes.fr/sudoc/regles/Catalogage/Spec_Loc_Auto.htm'>service de localisation automatique</a> qui se base sur les ISBN pour rattacher les exemplaires d'un �tablissement aux notices Sudoc existantes. Dans le cas des ouvrages parus r�cemment il n'y a pas de soucis avec la m�thode propos�e (utilisation des codes barres �diteur), l'isbn-13 est identique � l'EAN (qu'on trouve sur le code barre).
			</p>
			<p>
				Pour les ouvrages un peu plus anciens en revanche l'EAN n'est pas forc�ment pr�sent dans le sudoc et l'exemplarisation ne pourra �tre faite automatiquement. Exemple avec l'EAN 9782747599825 (<a href='http://www.sudoc.abes.fr/DB=2.1/SET=3/TTL=1/CMD?ACT=SRCHA&IKT=7&SRT=RLV&TRM=9782747599825'>0 r�sultat dans le sudoc</a>), � partir duquel on peut pourtant calculer informatiquement l'ISBN-10 : 2747599825 (<a href='http://www.sudoc.abes.fr/DB=2.1/SET=3/TTL=1/CMD?ACT=SRCHA&IKT=7&SRT=RLV&TRM=2747599825'>1 r�sultat dans le sudoc</a>)</p>
			<p>
				L'id�e est donc de partir d'une liste des EAN et pour chacun v�rifier automatiquement si la valeur est pr�sente dans l'index ISBN du sudoc (auquel cas on le conserve sous cette forme), sinon on le transforme en ISBN-10 afin de maximiser les chances de pouvoir faire la localisation automatique.
			</p>
			<p>
		<?php
			if (!$_FILES['fichier']) :
		?>
				S�lectionner le fichier � traiter (un ean par ligne) :
				<form action="ean_sudoc.php" enctype="multipart/form-data" method="POST">
					<input name="fichier" type="file" size="50" maxlength="100000" accept="text/*"/>
					<input type='submit' value='Envoyer'/>
				</form>
		<?php
			else:
				// On va faire le traitement du fichier
				$tmp_name = $_FILES['fichier']['tmp_name'];
				$file_name = basename($tmp_name);
				move_uploaded_file($tmp_name, "tmp/$file_name");
		?>
		<script type='text/javascript'>
		google.load("visualization", "1", {packages:["piechart", "corechart", "geomap"]});


		function drawChart(code, ok, total, mon_titre) {
			var data = new google.visualization.DataTable();
			data.addColumn('string', 'match');
			data.addColumn('number', 'pct');
			data.addRows([
				['OK',    ok],
				['KO',      total - ok]
			]);

			var options = {
				width: 400, height: 300,
				title: mon_titre,
				chartArea:{width:"100%",height:"75%"},
				legend:{position:"bottom"},
				colors:['#FF9900','#CCC']
			};

			var chart = new google.visualization.PieChart(document.getElementById(code));
			chart.draw(data, options);
		}


		$(document).ready(function() {
			$.getJSON('ean_sudoc_backoffice.php?filename=<?php echo $file_name; ?>', function(data) {
				var items = [];
				
				if (data["statut"] == "0")
				{
					$("#statut_op").html("Erreur lors de la transformation");
					$("#statut_op").css("color", "red");
					$("#statut_op").css("font-weight", "bold");
					$("#code_erreur").html(data["res"]);
					$("#code_erreur").css("visiblity", "visible");
				}
				else
				{
					// On cache la fl�che qui tourne
					$("#statut_op").remove();
					$("#code_erreur").remove();
					$("#resultat_transfo").css("visibility", "visible");
					
					// On va afficher ce qui nous int�resse
					sortie = "";
					sortie = "<a href='" + data["url_fic"] + "'>T�l�charger le fichier transform� (";
					
					if (data["stats"]["pct_augment"] != 0)
					{
						sortie += "+ " + data["stats"]["pct_augment"] + "% de documents recouverts";
					}
					
					sortie += ")</a>";
					// On ajoute les graphiques
					google.setOnLoadCallback(drawChart("chart1", data["stats"]["nb_init"], data["stats"]["nb_total"], "Notices 'recouvertes' avant correction isbn"));
					google.setOnLoadCallback(drawChart("chart2", data["stats"]["nb_v2"], data["stats"]["nb_total"], "Notices 'recouvertes' apr�s correction isbn"));
					
					var dataChart = new google.visualization.DataTable();
					dataChart.addColumn('string', 'Nombre de r�sultats');
					dataChart.addColumn('number', 'Nb');
					for (var num in data["repartition"])
					{
						dataChart.addRow([num, data["repartition"][num]]);
	        }
					var chart3 = new google.visualization.ColumnChart(document.getElementById('chart3'));
					chart3.draw(dataChart);

					// google.setOnLoadCallback(drawChart("chart3", data["stats"]["nb_v2"], data["stats"]["nb_total"], "Notices 'recouvertes' apr�s correction isbn"));
					$("#resultat_transfo").html(sortie);
				}

			}).error(function()
				{
					$("#statut_op").html("Erreur lors de la transformation. Code: JSON mal form� [<?php echo $file_name; ?>]");
					$("#statut_op").css("color", "red");
					$("#statut_op").css("font-weight", "bold");
					$("#code_erreur").css("visiblity", "visible");
				}
			);
  
		});
		</script>
		<p id='code_erreur' style='visiblity:hidden'>&nbsp;</p>
		<div id="resultat_transfo" style='border-top:1px solid black;'>
			&nbsp;
		</div>
		<p id='statut_op'><img src='/img/ajax-loader.gif'/></p>
		<div id="chart1" style='width:49%; float:left;'>&nbsp;</div>
		<div id="chart2" style='width:49%; float:right;'>&nbsp;</div>
		<div id="chart3" style='width:100%; height: 500px;'>&nbsp;</div>
		
		<?php
			endif;
		?>
			</p>
		</div>
	</div>
<?php
	include('../google.php');
?>
</body>
</html>

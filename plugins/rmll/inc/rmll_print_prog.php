<?php
		// Notes XML :
		// une conf peut dépasser en temps dans la pause suivante
	require_once _DIR_PLUGIN_RMLL.'rmll_mes_options.php';
	require_once _DIR_PLUGIN_RMLL.'rmll_mes_fonctions.php';
	require_once _DIR_PLUGIN_RMLL.'inc/rmll.class.php';

// PHV from http://fr.php.net/manual/fr/function.var-dump.php
//
function xmlvardump(){
	ob_start();
	$var = func_get_args();
	call_user_func_array('var_dump', $var);
	echo htmlspecialchars(ob_get_clean());
}

// Output an XML node $node with $txt content. Optional: an attribute $attr with $attr_txt value
function out_node($node, $txt = '', $attr = false, $attr_txt = false) {
	if($attr)
	    $attr = " $attr='".htmlspecialchars($attr_txt)."'";
	echo "\t<$node$attr>\t\t".htmlspecialchars($txt)."\n</$node>\n";
}

class RmllSchedule {
	public $themes_rubriques = null;
	public $periods = null;
	public $days = null;
	public $confObj = null;
	public $page = '';

	function RmllSchedule() {
		$this->periods =  $GLOBALS['rmll_periods'];
		$this->confObj = new Rmll_Conference();
		$this->page = isset($_GET['page']) ? trim($_GET['page']) : '';
	}

	function display_conf_salle(&$c) {
		$ret = array();
		if ($c['nature_code'] != '')
			$ret[] = _T('rmll:nature_code_'.$c['nature_code']);
		if ($c['salle'] != '')
			$ret[] = $c['salle'];
		$ret = implode(', ', $ret);
		if ($ret != '') {
			$ret = '('.$ret.')';
		}
		return $ret;
	}

	function display_keyword_selector(&$keywords, $selected = null) {
		foreach($keywords as $id => $title) {
			$keywords[$id] = supprimer_numero(extraire_multi(nettoyer_raccourcis_typo($title)));
		}
		asort($keywords);
	?>
	<?php
	}

	function display_theme_selector(&$themes, $selected = null) {
		foreach($themes as $id => $title) {
			$themes[$id] = supprimer_numero(extraire_multi(nettoyer_raccourcis_typo($title)));
		}
		asort($themes);
		// Ici s'affichait le formulaire avec listes de choix
	}

	function display_room_selector(&$rooms, $selected = null) {
		foreach($rooms as $id => $title) {
			$rooms[$id] = supprimer_numero(extraire_multi(nettoyer_raccourcis_typo($title)));
		}
		asort($rooms);
	}

	function display_day_selector($days, $selected = null) {
		if (!empty($days)) {
		}
	}

	function display() {
		$day = isset($_GET['d']) ? trim($_GET['d']) : (isset($_POST['d']) ? trim($_POST['d']) : '');
		$theme = isset($_GET['t']) ? trim($_GET['t']) : (isset($_POST['t']) ? trim($_POST['t']) : '');
		$keyword = isset($_GET['k']) ? trim($_GET['k']) : (isset($_POST['k']) ? trim($_POST['k']) : '');
		$room = isset($_GET['r']) ? trim($_GET['r']) : (isset($_POST['r']) ? trim($_POST['r']) : '');
		if ($day != '') {
			$this->display_day($day);
		}
		elseif ($theme != '') {
			$this->display_theme($theme);
		}
		else {
			$this->display_all($keyword, $room);
		}
	}

	function extract($datas, $theme_id = null, $day = null, $keyword = 0, $room = 0) {
		$conf = $alldays = $days = $allthemes = $themes = $allrooms = $allkeywords = array();
		for($i=0, $n=count($datas); $i<$n; $i++) {
			$articles = $datas[$i]['articles'];
			$new_articles = array();
			if (!empty($articles)) {
				foreach($articles as $article) {
					$article['data']['start'] = sprintf("%02d:%02d",
							$article['data']['heure'], $article['data']['minute']);
					if (!($article['data']['heure'] === null || $article['data']['minute'] === null)) {
						$room_filter  = $room != 0 && $article['data']['id_salle'] != $room;
						$keyword_filter = $keyword > 0;
						if (!empty($article['keywords'])) {
							foreach($article['keywords'] as $keyid => $keytitle) {
								if ($keyword_filter && $keyid == $keyword) {
									$keyword_filter = false;
								 }
								if (!array_key_exists($keyid, $allkeywords)) {
									$allkeywords[$keyid] = $keytitle;
								}
							}
						}
						if ($day !== null) {
							if ($article['data']['jour'] == $day) {
								$new_articles[] = $article;
								if (!array_key_exists($datas[$i]['id'], $themes)) {
									$themes[$datas[$i]['id']] = $datas[$i]['titre'];
								}
							}
						}
						else {
							if (!$keyword_filter && !$room_filter) {
								$new_articles[] = $article;
								if (!array_key_exists($datas[$i]['id'], $themes)) {
									$themes[$datas[$i]['id']] = $datas[$i]['titre'];
								}
							}
						}
						if (!in_array($article['data']['jour'], $alldays)) {
							$alldays[] = $article['data']['jour'];
						}
						if ($theme_id !== null) {
							if ($datas[$i]['id'] == $theme_id) {
								if (!in_array($article['data']['jour'], $days)) {
									$days[] = $article['data']['jour'];
								}
							}
						}
						else {
							if (!$keyword_filter && !$room_filter) {
								if (!in_array($article['data']['jour'], $days)) {
									$days[] = $article['data']['jour'];
								}
							}
						}
						if (!array_key_exists($datas[$i]['id'], $allthemes)) {
							$allthemes[$datas[$i]['id']] = $datas[$i]['titre'];
						}
						if ($article['data']['id_salle'] > 0 && !array_key_exists($article['data']['id_salle'], $allrooms)) {
							$allrooms[$article['data']['id_salle']] = $article['data']['salle'];
						}
					}
				}
				usort($new_articles, 'time_sorter');
				$datas[$i]['articles'] = $new_articles;
			}
			if (!empty($datas[$i]['articles'])) {
				if ($theme_id !== null) {
					if ($datas[$i]['id'] == $theme_id) {
						$conf[] = $datas[$i];
					}
				}
				else {
					$conf[] = $datas[$i];
				}
			}
		}
		sort($days);
		sort($alldays);

		return array($conf, $alldays, $days, $allthemes, $themes, $allrooms, $allkeywords);
	}

	function display_all($keyword = null, $room = null) {
		$datas = $this->confObj->get_confs(explode(',', RMLL_SESSION_ID), $GLOBALS['lang'], RMLL_KEYWORDS_GROUP_ID);
		list($conf, $alldays, $days, $allthemes, $themes, $allrooms, $allkeywords) = $this->extract($datas, null, null, $keyword, $room);
		if (!empty($alldays)) {
			$this->display_day_selector($alldays);
		}
		if (!empty($allthemes)) {
			$this->display_theme_selector($allthemes);
		}
		if (!empty($allrooms)) {
			$this->display_room_selector($allrooms, $room);
		}
		if (!empty($allkeywords)) {
			$this->display_keyword_selector($allkeywords, $keyword);
		}
		if (!empty($conf)) {
			$nb_themes = count($themes);

		// L'output de XML commence

		// Liste des thèmes
		?>
		<themes>
		<?php
			for($i=0; $i<$nb_themes; $i++) {
				$themestr = supprimer_numero(extraire_multi(nettoyer_raccourcis_typo($conf[$i]['titre'])));
		        echo	"\t\t<item_theme>" .
						htmlspecialchars($themestr) .
						"</item_theme>\n";
			}
		?>
		</themes>


		<?php
		// boucles imbriquées sur jour, timeslot, confs
		foreach($days as $j) {
			$daystr = ucfirst(nom_jour($j)).' '.jour($j);
		?>
	<day>
		<name><?php echo htmlspecialchars($daystr); ?></name>
						<?php
							$k = -1;
							foreach($this->periods as $p) {
								$k++;
								if ($p['type'] == RMLL_PERIOD_PAUSE) {
									?><pause start="<?php printf("%s", $p['start']); ?>" end="<?php printf("%s", $p['end']); ?>">
										<?php echo htmlspecialchars(_T('rmll:pause_pause')); ?>
									</pause>

									<?php
								}
								elseif ($p['type'] == RMLL_PERIOD_LUNCH) {
									?><lunch start="<?php printf("%s", $p['start']); ?>" end="<?php printf("%s", $p['end']); ?>">
										<?php echo htmlspecialchars(_T('rmll:pause_lunch')); ?>
									</lunch>
									<?php
								}
								elseif ($p['type'] == RMLL_PERIOD_CONF) {
								?>
									<confs start="<?php printf("%s", $p['start']); ?>" end="<?php printf("%s", $p['end']); ?>">
									<?php
									for($i=0; $i<$nb_themes; $i++) {
										$articles = $conf[$i]['articles'];
										foreach($articles as $cf) {
											$articlestr = supprimer_numero(extraire_multi($cf['data']['titre']));
											if ($cf['data']['jour'] != $j) {
												continue;
											}
											// si une conf dépasse dans la pause suivante on l'affiche
											if (isset($rmll_periods[$k+1]) && $rmll_periods[$k+1]['type'] != RMLL_PERIOD_CONF &&
												$cf['data']['start'] >= $p['start'] && $cf['data']['start'] < $rmll_periods[$k+1]['end']);
											elseif (!($cf['data']['start'] >= $p['start'] && $cf['data']['start'] <= $p['end']))
												continue;
											$time = get_slot_interval($cf['data']['heure'], $cf['data']['minute'], $cf['data']['duree']);
											$bloc_id = 'rmll-schedule-'.$cf['data']['id_article'];
									?>
                                        <conf>
											<title>
											<?php
												echo htmlspecialchars($articlestr);
											?>
											</title>
											<?php
											if (!empty($cf['data']['drap'])) {
												$codelang = $cf['data']['drap'];
												$nomlang = _T($codelang);
												out_node('lang', $nomlang, 'id', $codelang);
											}

											if(!empty($cf['data']['salle'])) {
											    out_node('room', $cf['data']['salle'], 'id', $cf['data']['id_salle']);
											}

											// img: src plugins/rmll/img_pack/flags/$codelang.png et alt $nomlang)

											//echo $time;
											//echo $this->display_conf_salle($cf['data']);
											if (!empty($cf['data']['intervenants'])) {
												out_node('speaker', $cf['data']['intervenants']);
											}

											if(!empty($cf['data']['nature'])) {
											    out_node('nature', $cf['data']['nature'], 'id', $cf['data']['nature_code']);
											}

											if(!empty($cf['data']['niveau'])) {
											    out_node('level', $cf['data']['niveau'], 'id', $cf['data']['id_niveau']);
											}

											// id_article dans SPIP
										    if(!empty($cf['id'])) {
												out_node('spip_article', $cf['id']);
											}
										    // id de la conf dans le plugin RMLL
											if(!empty($cf['data']['id_conference'])) {
											    out_node('id_conference', $cf['data']['id_conference']);
											}

											// jour format yyyy-mm-dd et n° du jour en partant de 1 = 1er jour
											if(!empty($cf['data']['jour'])) {
											    out_node('jour', $cf['data']['jour'], 'id', $cf['data']['id_jour']);
											}

											if(!empty($cf['data']['video'])) {
											    out_node('video', $cf['data']['video']);
											}

											if(!empty($cf['data']['notes'])) {
											    out_node('notes', $cf['data']['notes']);
											}

											if(!empty($cf['data']['descriptif'])) {
											    out_node('shortdesc', $cf['data']['descriptif']);
											}

											if(!empty($cf['data']['texte'])) {
											    out_node('text', $cf['data']['texte']);
											}


            	                            ?>
                                    	</conf>
                                    <?php
										}
									}
									?>
									</confs>
			<?php
								}
							}
			?>
	</day>
	<?php
			}
		}//fin if()
	}

	function display_day($day) {
		$datas = $this->confObj->get_confs(explode(',', RMLL_SESSION_ID), $GLOBALS['lang'], RMLL_KEYWORDS_GROUP_ID);
		list($conf, $alldays, $days, $allthemes, $themes, $allrooms, $allkeywords) = $this->extract($datas, null, $day);
		if (!empty($alldays)) {
			$this->display_day_selector($alldays, $day);
		}
		if (!empty($allthemes)) {
			$this->display_theme_selector($allthemes, $theme_id);
		}
		if (!empty($allrooms)) {
			$this->display_room_selector($allrooms, $room);
		}
		if (!empty($allkeywords)) {
			$this->display_keyword_selector($allkeywords);
		}
		if (!empty($conf)) {
			$nb_themes = count($themes);
			?>
			<div class="rmll-schedule-wrap rmll-schedule-date-wrap">
				<table class="rmll-schedule">
					<tr class="header">
						<th class="timeslot">&nbsp;</th>
						<?php
							for($i=0; $i<$nb_themes; $i++) {
								$themestr = supprimer_numero(extraire_multi(nettoyer_raccourcis_typo($conf[$i]['titre'])));
						?>
							<th class="conf">
								<a
									href="spip.php?page=<?php echo $this->page; ?>&amp;lang=<?php echo $GLOBALS['lang']; ?>&amp;t=<?php echo $conf[$i]['id']; ?>"
									title="<?php echo _T('rmll:accessible_link_theme').' « '.$themestr.' »'; ?>"
								>
									<?php echo $themestr; ?>
								</a>
							</th>
						<?php
							}
						?>
					</tr>
					<?php
						$k = -1;
						foreach($this->periods as $p) {
							$k++;
							if ($p['type'] == RMLL_PERIOD_PAUSE) {
								?><tr>
									<th class="timeslot"><?php printf("%s-%s", $p['start'], $p['end']); ?></th>
									<td class="pause" colspan="<?php echo $nb_themes; ?>"><?php echo _T('rmll:pause_pause'); ?></td>
									</tr>
								<?php
							}
							elseif ($p['type'] == RMLL_PERIOD_LUNCH) {
								?><tr>
									<th class="timeslot"><?php printf("%s-%s", $p['start'], $p['end']); ?></th>
									<td class="pause" colspan="<?php echo $nb_themes; ?>"><?php echo _T('rmll:pause_lunch'); ?></td>
									</tr>
								<?php
							}
							elseif ($p['type'] == RMLL_PERIOD_CONF) {
							?>
								<tr>
									<th class="timeslot"><?php printf("%s-%s", $p['start'], $p['end']); ?></th>
									<?php
										for($i=0; $i<$nb_themes; $i++) {
										?>
											<td class="conf">
											<?php
												$articles = $conf[$i]['articles'];
												foreach($articles as $cf) {
													$articlestr = supprimer_numero(extraire_multi($cf['data']['titre']));
													// si un conf dépasse dans la pause suivante on l'affiche
													if (isset($rmll_periods[$k+1]) && $rmll_periods[$k+1]['type'] != RMLL_PERIOD_CONF &&
														$cf['data']['start'] >= $p['start'] && $cf['data']['start'] < $rmll_periods[$k+1]['end']);
													elseif (!($cf['data']['start'] >= $p['start'] && $cf['data']['start'] <= $p['end']))
														continue;
													$time = get_slot_interval($cf['data']['heure'], $cf['data']['minute'], $cf['data']['duree']);
													$bloc_id = 'rmll-schedule-'.$cf['data']['id_article'];
												?>
													<div class="infos conf-color-<?php  echo get_color_theme($conf[$i]['id']); ?>">
														<div class="hider" id="<?php echo $bloc_id; ?>">
															<div class="title">
																<?php if (!empty($cf['data']['drap'])) { ?>
																	<img class="drap" src="plugins/rmll/img_pack/flags/<?php echo $cf['data']['drap'] ?>.png" alt="" />
																<?php } ?>
																<a
																	href="spip.php?article<?php echo $cf['data']['id_article']; ?>&amp;lang=<?php echo $GLOBALS['lang']; ?>"
																	title="<?php echo _T('rmll:accessible_link_article').' « '.$articlestr.' »'; ?>"
																>
																	<?php  echo $articlestr; ?>
																</a>
															</div>
															<div class="time">
																<span><?php echo $time; ?></span>
																<?php echo $this->display_conf_salle($cf['data']); ?>
															</div>
															<?php if (!empty($cf['data']['intervenants'])) { ?>
																<div class="speaker">
																	<?php echo $cf['data']['intervenants']; ?>
																</div>
															<?php } ?>
														</div>
													</div>
												<?php
												}
											?>
											</td>
										<?php
										}
									?>
								</tr>
							<?php
						}
					}
					?>
				</table>
			</div>
			<?php
		}
	}

	function display_theme($theme_id) {
		$datas = $this->confObj->get_confs(explode(',', RMLL_SESSION_ID), $GLOBALS['lang'], RMLL_KEYWORDS_GROUP_ID);
		list($conf, $alldays, $days, $allthemes, $themes, $allrooms, $allkeywords) = $this->extract($datas, $theme_id);
		if (!empty($alldays)) {
			$this->display_day_selector($alldays);
		}
		if (!empty($allthemes)) {
			$this->display_theme_selector($allthemes, $theme_id);
		}
		if (!empty($allrooms)) {
			$this->display_room_selector($allrooms, $room);
		}
		if (!empty($allkeywords)) {
			$this->display_keyword_selector($allkeywords);
		}
		if (!empty($conf)) {
			$conf = $conf[0];
			?>
			<div class="rmll-schedule-wrap rmll-schedule-theme-wrap">
				<table class="rmll-schedule">
					<tr class="header">
						<th class="timeslot">&nbsp;</th>
						<?php
							foreach($days as $j) {
								$daystr = ucfirst(nom_jour($j)).' '.jour($j);
						?>
							<th class="conf">
								<a
									href="spip.php?page=<?php echo $this->page; ?>&amp;lang=<?php echo $GLOBALS['lang']; ?>&amp;d=<?php echo $j; ?>"
									title="<?php echo _T('rmll:accessible_link_jour').' '.$daystr; ?>"
								>
									<?php echo $daystr; ?>
								</a>
							</th>
						<?php
							}
						?>
					</tr>
					<?php
						$k = -1;
						foreach($this->periods as $p) {
							$k++;
							if ($p['type'] == RMLL_PERIOD_PAUSE) {
								?><tr>
									<th class="timeslot"><?php printf("%s-%s", $p['start'], $p['end']); ?></th>
									<td class="pause" colspan="<?php echo count($days); ?>"><?php echo _T('rmll:pause_pause'); ?></td>
									</tr>
								<?php
							}
							elseif ($p['type'] == RMLL_PERIOD_LUNCH) {
								?><tr>
									<th class="timeslot"><?php printf("%s-%s", $p['start'], $p['end']); ?></th>
									<td class="pause" colspan="<?php echo count($days); ?>"><?php echo _T('rmll:pause_lunch'); ?></td>
									</tr>
								<?php
							}
							elseif ($p['type'] == RMLL_PERIOD_CONF) {
							?>
								<tr>
									<th class="timeslot"><?php printf("%s-%s", $p['start'], $p['end']); ?></th>
									<?php
										foreach($days as $j) {
										?>
											<td class="conf">
											<?php
												$articles = $conf['articles'];
												foreach($articles as $cf) {
													$articlestr = supprimer_numero(extraire_multi($cf['data']['titre']));
													if ($cf['data']['jour'] != $j)
														continue;
													// si un conf dépasse dans la pause suivante on l'affiche
													if (isset($this->periods[$k+1]) && $this->periods[$k+1]['type'] != RMLL_PERIOD_CONF &&
														$cf['data']['start'] >= $p['start'] && $cf['data']['start'] < $rthis->periods[$k+1]['end']);
													elseif (!($cf['data']['start'] >= $p['start'] && $cf['data']['start'] <= $p['end']))
														continue;
													$time = get_slot_interval($cf['data']['heure'], $cf['data']['minute'], $cf['data']['duree']);
												?>
													<div class="infos conf-color-<?php  echo get_color_theme($theme_id); ?>">
														<div class="title">
															<?php if (!empty($cf['data']['drap'])) { ?>
																<img class="drap" src="plugins/rmll/img_pack/flags/<?php echo $cf['data']['drap'] ?>.png" alt="" />
															<?php } ?>
															<a
																href="spip.php?article<?php echo $cf['data']['id_article']; ?>&amp;lang=<?php echo $GLOBALS['lang']; ?>"
																title="<?php echo _T('rmll:accessible_link_article').' « '.$articlestr.' »'; ?>"
															>
																<?php  echo $articlestr; ?>
															</a>
														</div>
														<div class="time">
															<span><?php echo $time; ?></span>
															<?php echo $this->display_conf_salle($cf['data']); ?>
														</div>
														<?php if (!empty($cf['data']['intervenants'])) { ?>
															<div class="speaker">
																<?php echo $cf['data']['intervenants']; ?>
															</div>
														<?php } ?>
													</div>
												<?php
												}
											?>
											</td>
										<?php
										}
									?>
								</tr>
							<?php
						}
					}
					?>
				</table>
			</div>
			<?php
		}
	}

	function display_acc_theme_selector(&$themes, $selected = null) {
		foreach($themes as $id => $title) {
			$themes[$id] = supprimer_numero(extraire_multi(nettoyer_raccourcis_typo($title)));
		}
		asort($themes);
		?>
		<div>
			<strong><?php echo _T('rmll:affichage_theme'); ?></strong>
		</div>
		<ul>
		<?php
			foreach($themes as $k => $v) {
		?>
			<li>
		<?php
				if ($k != $selected) {
				?>
					<a
						href="spip.php?page=<?php echo $this->page; ?>&amp;lang=<?php echo $GLOBALS['lang']; ?>&amp;t=<?php echo $k; ?>"
						title="<?php echo _T('rmll:accessible_link_theme').' « '.$v.' »'; ?>"
						>
				<?php } ?>
					<?php echo $v; ?>
				<?php if ($k != $selected) { ?>
					</a>
				<?php } ?>
			</li>
			<?php
			}
		?>
		</ul>
		<?php
	}

	function display_acc_room_selector(&$rooms, $selected = null) {
		foreach($rooms as $id => $title) {
			$rooms[$id] = supprimer_numero(extraire_multi(nettoyer_raccourcis_typo($title)));
		}
		asort($rooms);
		?>
		<div>
			<strong><?php echo _T('rmll:affichage_room'); ?></strong>
		</div>
		<ul>
		<?php
			foreach($rooms as $k => $v) {
		?>
			<li>
		<?php
				if ($k != $selected) {
				?>
					<a
						href="spip.php?page=<?php echo $this->page; ?>&amp;lang=<?php echo $GLOBALS['lang']; ?>&amp;r=<?php echo $k; ?>"
						title="<?php echo _T('rmll:accessible_link_room').' « '.$v.' »'; ?>"
						>
				<?php } ?>
					<?php echo $v; ?>
				<?php if ($k != $selected) { ?>
					</a>
				<?php } ?>
			</li>
			<?php
			}
		?>
		</ul>
		<?php
	}

	function display_acc_day_selector(&$days, $selected = null) {
		if (!empty($days)) {
			?>
				<div>
					<strong><?php echo _T('rmll:affichage_jour'); ?></strong>
				</div>
				<ul>
			<?php
				foreach($days as $day) {
					$daystr = ucfirst(nom_jour($day)).' '.jour($day);
			?>
					<li>
						<?php if ($day !== $selected) { ?>
							<a
								href="spip.php?page=<?php echo $this->page; ?>&amp;lang=<?php echo $GLOBALS['lang']; ?>&amp;d=<?php echo $day; ?>"
								title="<?php echo _T('rmll:accessible_link_jour').' '.$daystr; ?>"
								>
						<?php } ?>
							<?php echo $daystr; ?>
						<?php if ($day !== $selected) { ?>
							</a>
						<?php } ?>
					</li>
			<?php
				}
			?>
				</ul>
			<?php
		}
	}

	function display_acc_keyword_selector(&$keywords, $selected = null) {
		foreach($keywords as $id => $title) {
			$keywords[$id] = supprimer_numero(extraire_multi(nettoyer_raccourcis_typo($title)));
		}
		asort($keywords);
	?>
		<div>
			<strong><?php echo _T('rmll:affichage_motcle'); ?></strong>
		</div>
		<ul>
		<?php
			foreach($keywords as $k => $v) {
		?>
			<li>
		<?php
				if ($k != $selected) {
				?>
					<a
						href="spip.php?page=<?php echo $this->page; ?>&amp;lang=<?php echo $GLOBALS['lang']; ?>&amp;k=<?php echo $k; ?>"
						title="<?php echo _T('rmll:accessible_link_theme_transversal').' « '.$v.' »'; ?>"
						>
				<?php } ?>
					<?php echo $v; ?>
				<?php if ($k != $selected) { ?>
					</a>
				<?php } ?>
			</li>
			<?php
			}
		?>
		</ul>
		<?php
	}



	function display_acc() {
		$day = isset($_GET['d']) ? $_GET['d'] : '';
		$theme = isset($_GET['t']) ? $_GET['t'] : '';
		$keyword = isset($_GET['k']) ? $_GET['k'] : '';
		$room = isset($_GET['r']) ? $_GET['r'] : '';

		$datas = $this->confObj->get_confs(explode(',', RMLL_SESSION_ID), $GLOBALS['lang'], RMLL_KEYWORDS_GROUP_ID);
		if (!empty($theme)) {
			list($conf, $alldays, $days, $allthemes, $themes, $allrooms, $allkeywords) = $this->extract($datas, $theme);
		}
		elseif (!empty($room)) {
			list($conf, $alldays, $days, $allthemes, $themes, $allrooms, $allkeywords) = $this->extract($datas, null, null, null, $room);
		}
		elseif (!empty($keyword)) {
			list($conf, $alldays, $days, $allthemes, $themes, $allrooms, $allkeywords) = $this->extract($datas, null, null, $keyword);
		}
		else {
			list($conf, $alldays, $days, $allthemes, $themes, $allrooms, $allkeywords) = $this->extract($datas, null, $day);
		}

		$this->display_acc_day_selector($alldays, $day);
		$this->display_acc_theme_selector($allthemes, $theme);
		$this->display_acc_room_selector($allrooms, $room);
		$this->display_acc_keyword_selector($allkeywords, $keyword);

		?>
			<a id="skipcontent"></a>
		<?php

		if (!empty($theme)) {
			$this->display_acc_theme($theme, $days, $themes, $conf);
		}
		elseif (!empty($room)) {
			$this->display_acc_filter(null, $room, $days, $themes, $allkeywords, &$conf);
		}
		elseif (!empty($keyword)) {
			$this->display_acc_filter($keyword, null, $days, $themes, $allkeywords, &$conf);
		}
		else {
			$this->display_acc_day($day, $themes, $conf);
		}
	}

	function display_acc_day($day, &$themes, &$conf) {
		if (!empty($day)) {
		?>
			<h2>
				<?php echo ucfirst(nom_jour($day)).' '.jour($day); ?>
			</h2>
		<?php
		}

		if (!empty($conf)) {
			$nb_themes = count($themes);
			?>
			<div class="rmll-schedule-wrap rmll-schedule-date-wrap">
				<div class="rmll-schedule">
				<?php
					for($i=0; $i<$nb_themes; $i++) {
				?>
						<h3>
							<?php echo supprimer_numero(extraire_multi(nettoyer_raccourcis_typo($conf[$i]['titre']))); ?>
						</h3>
						<?php
							if (!isset($conf[$i])) {
								continue;
							}
							$articles = $conf[$i]['articles'];
							foreach($articles as $cf) {
								$articlestr = supprimer_numero(extraire_multi($cf['data']['titre']));
								$time = get_slot_interval($cf['data']['heure'], $cf['data']['minute'], $cf['data']['duree']);
								$bloc_id = 'rmll-schedule-'.$cf['data']['id_article'];
							?>
								<div class="conf">
									<div class="infos conf-color-<?php  echo get_color_theme($conf[$i]['id']); ?>">
										<div class="title">
											<?php if (!empty($cf['data']['drap'])) { ?>
												<img class="drap" src="plugins/rmll/img_pack/flags/<?php echo $cf['data']['drap'] ?>.png" alt="" />
											<?php } ?>
											<a
												href="spip.php?article<?php echo $cf['data']['id_article']; ?>&amp;lang=<?php echo $GLOBALS['lang']; ?>"
												title="<?php echo _T('rmll:accessible_link_article').' « '.$articlestr.' »'; ?>"
												>
												<?php  echo $articlestr; ?>
											</a>
										</div>
										<div class="time">
											<span><?php echo $time; ?></span>
											<?php echo $this->display_conf_salle($cf['data']); ?>
										</div>
										<?php if (!empty($cf['data']['intervenants'])) { ?>
											<div class="speaker">
												<?php echo $cf['data']['intervenants']; ?>
											</div>
										<?php } ?>
									</div>
								</div>
							<?php
							}
					}
				?>
				</div>
			</div>
			<?php
		}
	}

	function display_acc_theme($theme, &$days, &$themes, &$conf) {
		?>
			<h2>
				<?php echo supprimer_numero(extraire_multi(nettoyer_raccourcis_typo($themes[$theme]))); ?>
			</h2>
		<?php
		if (!empty($conf)) {
			?>
			<div class="rmll-schedule-wrap rmll-schedule-date-wrap">
				<div class="rmll-schedule">
				<?php
					foreach($days as $day) {
				?>
						<h3>
							<?php echo ucfirst(nom_jour($day)).' '.jour($day); ?>
						</h3>
						<?php
							for($i=0, $n=count($themes); $i<$n; $i++) {
								if (!isset($conf[$i])) {
									continue;
								}
								$articles = $conf[$i]['articles'];
								foreach($articles as $cf) {
									 if ($cf['data']['jour'] != $day) {
										continue;
									}
									$articlestr = supprimer_numero(extraire_multi($cf['data']['titre']));
									$time = get_slot_interval($cf['data']['heure'], $cf['data']['minute'], $cf['data']['duree']);
									$bloc_id = 'rmll-schedule-'.$cf['data']['id_article'];
								?>
									<div class="conf">
										<div class="infos conf-color-<?php  echo get_color_theme($conf[$i]['id']); ?>">
											<div class="title">
												<?php if (!empty($cf['data']['drap'])) { ?>
													<img class="drap" src="plugins/rmll/img_pack/flags/<?php echo $cf['data']['drap'] ?>.png" alt="" />
												<?php } ?>
												<a
													href="spip.php?article<?php echo $cf['data']['id_article']; ?>&amp;lang=<?php echo $GLOBALS['lang']; ?>"
													title="<?php echo _T('rmll:accessible_link_article').' « '.$articlestr.' »'; ?>"
													>
													<?php  echo $articlestr; ?>
												</a>
											</div>
											<div class="time">
												<span><?php echo $time; ?></span>
												<?php echo $this->display_conf_salle($cf['data']); ?>
											</div>
											<?php if (!empty($cf['data']['intervenants'])) { ?>
												<div class="speaker">
													<?php echo $cf['data']['intervenants']; ?>
												</div>
											<?php } ?>
										</div>
									</div>
								<?php
								}
							}
					}
				?>
				</div>
			</div>
			<?php
		}
	}

	function display_acc_filter($keyword, $room, &$days, &$themes, $keywords, &$conf) {
		if ($keyword !== null) {
		?>
			<h2>
				<?php echo supprimer_numero(extraire_multi(nettoyer_raccourcis_typo($keywords[$keyword]))); ?>
			</h2>
		<?php
		}
		if (!empty($conf)) {
			?>
			<div class="rmll-schedule-wrap rmll-schedule-date-wrap">
				<div class="rmll-schedule">
				<?php
					foreach($days as $day) {
				?>
						<h3>
							<?php echo ucfirst(nom_jour($day)).' '.jour($day); ?>
						</h3>
						<?php
							for($i=0, $n=count($themes); $i<$n; $i++) {
								if (!isset($conf[$i])) {
									continue;
								}
								$articles = $conf[$i]['articles'];
								$match = false;
								foreach($articles as $cf) {
									 if ($cf['data']['jour'] == $day) {
										$match = true;
										break;
									}
								}
								if ($match) {
								?>
									<h4>
										<?php echo supprimer_numero(extraire_multi(nettoyer_raccourcis_typo($conf[$i]['titre']))); ?>
									</h4>
								<?php
								}
								foreach($articles as $cf) {
									if ($cf['data']['jour'] != $day) {
										continue;
									}
									$articlestr = supprimer_numero(extraire_multi($cf['data']['titre']));
									$time = get_slot_interval($cf['data']['heure'], $cf['data']['minute'], $cf['data']['duree']);
									$bloc_id = 'rmll-schedule-'.$cf['data']['id_article'];
								?>
									<div class="conf">
										<div class="infos conf-color-<?php  echo get_color_theme($conf[$i]['id']); ?>">
											<div class="title">
												<?php if (!empty($cf['data']['drap'])) { ?>
													<img class="drap" src="plugins/rmll/img_pack/flags/<?php echo $cf['data']['drap'] ?>.png" alt="" />
												<?php } ?>
												<a
													href="spip.php?article<?php echo $cf['data']['id_article']; ?>&amp;lang=<?php echo $GLOBALS['lang']; ?>"
													title="<?php echo _T('rmll:accessible_link_article').' « '.$articlestr.' »'; ?>"
													>
													<?php  echo $articlestr; ?>
												</a>
											</div>
											<div class="time">
												<span><?php echo $time; ?></span>
												<?php echo $this->display_conf_salle($cf['data']); ?>
											</div>
											<?php if (!empty($cf['data']['intervenants'])) { ?>
												<div class="speaker">
													<?php echo $cf['data']['intervenants']; ?>
												</div>
											<?php } ?>
										</div>
									</div>
								<?php
								}
							}
					}
				?>
				</div>
			</div>
			<?php
		}
	}
}
?>

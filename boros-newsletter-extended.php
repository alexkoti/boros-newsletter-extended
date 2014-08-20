<?php
/*
Plugin Name: Boros Newsletter Extended
Plugin URI: http://alexkoti.com
Description: Plugin para cadastro de emails em tabela própria, com opção de exportar em xls, filtrado por data.
Version: 1.0.1
Author: Alex Koti
Author URI: http://alexkoti.com
License: GPL2
*/



/*
 * 
 * @TODO:
 * - REVISAR A POSSIBILIDADE DE USAR BOROS ELEMENTS
 * - Label opcional
 * - Rever melhorias na exibição do select de estados
 * - Melhorar templates
 * - AJAX FRONTEND!!!
 * - AJAX ADMIN >>> remover/editar entrada
 * - Melhorar a configuração para integrar os campos a serem gravados, listados no admin e download, deixando integrado.
 * - Criar mini-templates de inputs para form-elements
 * - Permitir diversos forms em uma só page.
 * - Integrar o js no plugin
 * - Unificar as variáveis do nome da tabela
 * - metodo add_data() que possa ser chamado diretamente por outros plugins/functions, por exemplo para salvar o email do commenter
 * - Verificar as datas pedidas para download, validar período
 * 
 * 
 * ROADMAP v2
 * - Página de configuração inicial: escolher nome e criar tabela
 * 
 * 
 */



function borosnews_config(){
	$form_model = array(
		'ipt_email' => array(
			'db_column' => 'person_email',
			'type' => 'text',
			'label' => 'Email',
			'placeholder' => 'Seu e-mail',
			'std' => '',
			'class' => 'required valid txt',
			'validate' => 'email',
			'required' => true,
			'accept_std' => false,
		),
		'ipt_nome' => array(
			'db_column' => 'person_name',
			'type' => 'text',
			'label' => 'Nome',
			'placeholder' => 'Seu nome',
			'std' => '',
			'class' => 'required txt',
			'validate' => 'string',
			'required' => true,
			'accept_std' => false,
		),
		'person_sobrenome' => array(
			'db_column' => 'person_metadata',
			'type' => 'text',
			'label' => 'Sobrenome',
			'placeholder' => 'Sobrenome',
			'std' => '',
			'class' => 'required valid txt',
			'validate' => 'string',
			'required' => true,
			'accept_std' => false,
		),
	);
	return $form_model;
}



/**
 * TESTES
 * 
 */
add_action( 'wp_footer', 'newsletter_test' );
//add_action( 'admin_footer', 'newsletter_test' );
function newsletter_test(){
	$newsletter = BorosNewsletter::init();
	pre($newsletter, 'BorosNewsletter');
	
	pre(get_class_vars('BorosNewsletter'), 'BorosNewsletter get_class_vars');
	pre(get_object_vars($newsletter), 'BorosNewsletter get_object_vars');
	
	global $wpdb;
	pre($wpdb, 'wpdb');
}

/**
 * Hook de ativação, para criar a tabela, se necessário
 * 
 */
register_activation_hook( __FILE__, array('BorosNewsletter', 'check_table') );

/**
 * Iniciar o plugin no processo corrente
 * 
 */
add_action( 'init', array('BorosNewsletter', 'init') );

class BorosNewsletter {
	
	/**
	 * Versão
	 * 
	 */
	$version = '1.0';
	
	/**
	 * Sinaliza se o plugin Boros Elements está ativado, pois depende das funcões de formulário deste.
	 * 
	 */
	var $boros_active = false;
	
	/**
	 * Nome da tabela, poderá ser modificado pela config
	 * DEIXAR ESSA CONFIGURAÇÂO EM UMA ADMIN PAGE
	 * 
	 */
	static $table_name = 'newsletter';
	
	/**
	 * Lista de elementos
	 * 
	 */
	var $elements = array();
	var $form_model = array();
	
	/**
	 * Erros
	 * 
	 */
	var $errors = array();
	
	/**
	 * Singleton: usar apenas uma instância da classe por requisição de página.
	 * Isso significa que qualqer chamada que utilize $newletter estará referenciando um único objeto.
	 * 
	 */
	private static $instance;
	
	public static function init(){
		if( empty( self::$instance ) ){
			self::$instance = new BorosNewsletter();
		}
		return self::$instance;
	}
	
	private function __construct(){
		// verificar dependência
		$this->dependecy_check();
		
		// registrar tabela no $wpdb
		$this->init_table();
		
		// actions
		add_action( 'admin_menu', array($this, 'admin_page') );
		add_action( 'admin_init', array('admin_remove_user') );
	}
	
	private function dependecy_check(){
		$this->boros_active = ( plugin_is_active('boros/boros.php') ? true : false;
	}
	
	/**
	 * Apenas iniciar a tabela dentro do $wpdb, para que possa ser utilizada por ele
	 * 
	 */
	private function init_table(){
		global $wpdb;
		$table_name = self::$table_name;
		$wpdb->$table_name = $wpdb->prefix . self::$table_name;
	}
	
	/**
	 * Página do admin
	 * 
	 */
	private function admin_page(){
		$admin_page = add_menu_page( 'Newsletter', 'Newsletter', 'edit_pages', 'newsletter_controls' , 'admin_page_output', 'dashicons-email' );
	}
	
	/**
	 * Remover usuário pelo link de (-) na listagem de usuários no admin
	 * @todo adicionar nonce
	 * 
	 */
	private function admin_remove_user(){
		if( isset($_GET['newsletter_action']) and $_GET['newsletter_action'] == 'remove' ){
			global $wpdb;
			$person_id = (int)$_GET['person_id'];
			$wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->newsletter} WHERE person_id = %d", $person_id ) );
		}
	}
	
	private function admin_page_output(){
		?>
		<div class="wrap">
			<h2><?php echo bloginfo('blogname'); ?> - Newsletter</h2>
			
			<form id="export_data" action="<?php echo self_url(); ?>" method="post">
				<h3>Exportar dados:</h3>
				<p>
					Data inicio: <br />
					<select name="start_dia" class="ipt_select_dia"><?php decimal_options_list(1, 31, 1, 2); ?></select>
					<select name="start_mes" class="ipt_select_mes"><?php decimal_options_list(1, 12, 1, 2); ?></select>
					<select name="start_ano" class="ipt_select_ano"><?php decimal_options_list(2011, 2020, 2011, 0); ?></select>
				</p>
				<p>
					Data fim: <br />
					<select name="end_dia" class="ipt_select_dia"><?php decimal_options_list(1, 31, date('d'), 2); ?></select>
					<select name="end_mes" class="ipt_select_mes"><?php decimal_options_list(1, 12, date('m'), 2); ?></select>
					<select name="end_ano" class="ipt_select_ano"><?php decimal_options_list(2011, 2020, date('Y'), 0); ?></select>
				</p>
				<input type="hidden" value="1" name="download_newsletter" />
				<input type="submit" value="Exportar" class="button-primary" name="submit_newsletter" />
			</form>
			
			<?php
				
				if( isset($_GET['pg']) ){
					$pg = $_GET['pg'];
				}
				else{
					$pg = 1;
				}
				// resultados por página
				if( isset($_GET['per_page']) ){
					$per_page = $_GET['per_page'];
				}
				else{
					$per_page = 10;
				}
				// offset
				$offset = ($pg - 1) * $per_page;
				
				global $wpdb, $post;
				$tabela = $wpdb->prefix . 'newsletter';
				
				//total de cadastros
				$total_cadastros = $wpdb->get_results("
						SELECT person_id
						FROM $tabela
				", ARRAY_A);
				
				// dados da página atual
				$cadastros = $wpdb->get_results("
						SELECT *
						FROM $tabela
						ORDER BY person_id DESC
						LIMIT $offset, $per_page
				", ARRAY_A);
				
				// total de páginas
				$total_paginas = ceil( count($total_cadastros) / $per_page );
				
				/**
				 * Definir as colunas extras de metadata.
				 * É preciso montar um array modelo para o caso de mudanças na quantidade de colunas de metadatdos e registros computados, por exemplo ao adicionar uma coluna 'modelo', 
				 * não exisitá diferenças entre a quantidade de headers(<th>) e dados exibidos <td>
				 * 
				 */
				$this->form_model = borosnews_config();
				$metadatas = array();
				$metadata_th = '';
				
				/**
				 * Filtrar as colunas de metadata, caso não deseje que alguma infomação não seja recuperada
				 * 
				 */
				$exclude_metadata_column = apply_filters( 'boros_newsletter_show_metadata_columns', array() );
				foreach( $this->form_model as $item => $attr ){
					if( $attr['db_column'] == 'person_metadata' and !in_array( $item, $exclude_metadata_column ) ){
						$metadatas[] = $item;
						$metadata_th .= "<th>{$attr['label']}</th>";
					}
				}
			?>
			<h3>Dados cadastrados:</h3>
			
			<table id="newsletter_data" class="widefat">
				<thead>
					<tr>
						<th class="check-column"></th>
						<th class="check-column">ID</th>
						<th>E-mail</th>
						<th>Nome</th>
						<?php echo $metadata_th; ?>
						<th>Data</th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th class="check-column"></th>
						<th class="check-column">ID</th>
						<th>E-mail</th>
						<th>Nome</th>
						<?php echo $metadata_th; ?>
						<th>Data</th>
					</tr>
				</tfoot>
			<?php
			foreach( $cadastros as $cad ){
				$args = array(
					'newsletter_action' => 'remove',
					'person_id' => $cad['person_id'],
				);
				ob_start();
			?>
				<tr>
					<td><a href="<?php echo add_query_arg( $args ); ?>" class="newsletter_remove_btn">Remover</a></td>
					<td class="check-column"><?php echo $cad['person_id']; ?></td>
					<td><?php echo $cad['person_email']; ?></td>
					<td><?php echo $cad['person_name']; ?></td>
					<?php
					// pode haver diferença entre a quantidade registrada de dados e as colunas exigidas - ver comentário mais acima, assim certifica-se que está sendo exibido apenas os dados pedidos
					$saved_metadatas = maybe_unserialize($cad['person_metadata']);
					foreach( $metadatas as $metadata ){
						// lidar com valores vazios - entradas antigas de antes de adicionar novos metas, ou quando aceita valores vazios
						$meta = ( isset($saved_metadatas[$metadata]) ) ? $saved_metadatas[$metadata] : '';
						// caso tenha sido configurado uma function de filtro de output, aplicar aqui:
						if( isset($this->form_model[$metadata]['admin_output']) ){
							$meta = call_user_func( $this->form_model[$metadata]['admin_output'], $meta );
						}
						echo "<td>{$meta}</td>";
					}
					?>
					<td>
						<?php echo ( $cad['person_date'] != '0000-00-00 00:00:00' ) ? mysql2date('d\/m\/Y', $cad['person_date']) : 'Sem data'; ?>
					</td>
				</tr>
			<?php
				$row = ob_get_contents();
				ob_end_clean();
				/**
				 * Filtro para verificar se é preciso pular alguma linha conforme os dados registrados
				 * 
				 */
				$skip_row = apply_filters( 'boros_newsletter_show_skip_row', false, $saved_metadatas );
				if( $skip_row === false ){
					echo $row;
				}
			}
			?>
			</table>
			<div class="tablenav form-table" style="height:auto;">
				<form action="<?php echo get_bloginfo('wpurl') . '/wp-admin/admin.php'; ?>">
					Resultados por página:
					<input type="hidden" name="page" value="newsletter_controls" />
					<input type="hidden" name="pg" value="1" />
					<input class="small-text" type="text" name="per_page" value="<?php echo $per_page; ?>" id="cadastros_per_page" /> 
					<input class="button-primary" type="submit" value="ok" />
				</form>
				<div class="tablenav-pages" style="height:auto;">
					<?php if( $pg > 1 ){ ?>
					<a href="<?php echo add_query_arg( array('pg' => $pg - 1, 'per_page' => $per_page) ); ?>">&laquo;</a>
					<?php } ?>
					
					<?php
						for( $i=1; $i <= $total_paginas; $i++ ){
							if( $i == $pg ){
								echo ' <span class="page-numbers current">' . $i . '</span> ';
							}
							else{
								echo '<a class="page-numbers" href="' . add_query_arg( array('pg' => $i, 'per_page' => $per_page) ) . '">' . $i . '</a> ';
							}
						}
					?>
					<?php
					if( $pg < $total_paginas ){
					?>
					<a href="<?php echo add_query_arg( array('pg' => $pg + 1, 'per_page' => $per_page) ); ?>">&raquo;</a>
					<?php } ?>
				</div>
				<p style="clear:both;text-align:right">Tempo de consulta: <?php echo timer_stop(0,3); ?> segundos.</p>
			</div>
		</div><!-- fim de WRAP -->
		<?php
	}
	
	/**
	 * Verificar se tabela de newsletter já existe, e caso contrário criá-la.
	 * 
	 * @todo usar esse método apenas no momento de consultar ou salvar dados, deixando o carregamento dde páginas simples sem este trecho.
	 * 
	 */
	static function check_table(){
		global $wpdb;
		$new_table_name = $wpdb->prefix . self::$table_name;
		
		// criar tabela se não existir e caso esteja habilitado no painel de controle
		if( $wpdb->get_var("SHOW TABLES LIKE '{$new_table_name}'") != $new_table_name ){
			if( !empty ($wpdb->charset) ){
				$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
			}
			
			if( !empty ($wpdb->collate) ){
				$charset_collate .= " COLLATE {$wpdb->collate}";
			}
			
			$sql = "
			CREATE TABLE IF NOT EXISTS `{$new_table_name}` (
				`person_id` int(20) unsigned NOT NULL AUTO_INCREMENT,
				`person_name` varchar(255) NOT NULL DEFAULT '',
				`person_email` varchar(255) NOT NULL DEFAULT '',
				`person_metadata` text NOT NULL DEFAULT '',
				`person_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY (`person_id`)
			) {$charset_collate} ;
			";
			
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}
	}
}




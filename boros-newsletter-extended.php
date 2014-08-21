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
	
	//global $wpdb;
	//pre($wpdb, 'wpdb');
}

/**
 * Hook de ativação, para criar a tabela, se necessário
 * 
 */
register_activation_hook( __FILE__, array('BorosNewsletter', 'check_table') );

/**
 * Iniciar o plugin no processo corrente
 * Prioridade última para poder aplicar filtros nas variáveis através de outros plugins
 * 
 */
add_action( 'init', array('BorosNewsletter', 'init'), 99 );

class BorosNewsletter {
	
	/**
	 * Versão
	 * 
	 */
	var $version = '1.0';
	
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
	 * Form count
	 * Determina a quantidade de forms exibidos na página. Cada vez que um form é exibido no frontend, esse contador aumenta.
	 * É necessário para definir diversos ids para o html dos forms, permitindo que as âncoras e retornos de erro dsejam 
	 * enviados para o form correto na página
	 * 
	 */
	static $form_count = 0;
	
	/**
	 * Formulários registrados
	 * 
	 */
	var $forms = array();
	
	/**
	 * Form options
	 * É o que definirá cada um dos forms de newsletter, pois poderá exisir mais de um na página/site, com diferentes 
	 * configurações, e deverão ser processados separadamente.
	 * 
	 */
	var $form_options = array(
		'form_id' => 'form_newsletter',
		'prepend' => '',
		'append' => '',
		'layout' => 'normal',
	);
	
	/**
	 * Opções do form
	 * 
	 */
	var $form_attrs = array(
		'id' => 'form-newsletter',
		'class' => 'form-newsletter',
	);
	
	/**
	 * Modelo padrão dos campos do formulário
	 * 
	 */
	var $form_model = array(
		'ipt_email' => array(
			'db_column' => 'person_email',
			'type' => 'text',
			'label' => 'Email',
			'placeholder' => 'Seu e-mail',
			'std' => '',
			'validate' => 'email',
			'required' => true,
			'accept_std' => false,
			'classes' => array(
				'form_group_class' => '',
				'label_class' => '',
				'input_class' => '',
				'input_col_class' => '',
			),
			'addon' => array(
				'before' => '',
				'after' => '',
			),
		),
		'ipt_nome' => array(
			'db_column' => 'person_name',
			'type' => 'text',
			'label' => 'Nome',
			'placeholder' => 'Seu nome',
			'std' => '',
			'validate' => 'string',
			'required' => true,
			'accept_std' => false,
			'classes' => array(
				'form_group_class' => '',
				'label_class' => '',
				'input_class' => '',
				'input_col_class' => '',
			),
			'addon' => array(
				'before' => '',
				'after' => '',
			),
		),
		'person_sobrenome' => array(
			'db_column' => 'person_metadata',
			'type' => 'text',
			'label' => 'Sobrenome',
			'placeholder' => 'Sobrenome',
			'std' => '',
			'validate' => 'string',
			'required' => true,
			'accept_std' => false,
			'classes' => array(
				'form_group_class' => '',
				'label_class' => '',
				'input_class' => '',
				'input_col_class' => '',
			),
			'addon' => array(
				'before' => '',
				'after' => '',
			),
			'append_submit' => false,
		),
		'submit' => array(
			'db_column' => 'skip',
			'type' => 'submit',
			'label' => 'Enviar',
			'validate' => false,
			'required' => false,
			'classes' => array(
				'form_group_class' => '',
				'label_class' => '',
				'input_class' => '',
				'input_col_class' => '',
			),
			'addon' => array(
				'before' => '',
				'after' => '',
			),
		),
	);
	
	/**
	 * Erros
	 * 
	 */
	var $form_errors = array();
	
	/**
	 * Dados válidos
	 * 
	 */
	var $form_data = array();
	
	/**
	 * Mensagens
	 * 
	 */
	var $form_messages = array(
		'error' => 'Ocorreram alguns erros, por favor verifique.',
		'success' => 'Formulário enviado com sucesso!',
		'blank' => '',
	);
	
	/**
	 * Status do form: blank, error, success
	 * 
	 */
	var $form_status = 'blank';
	
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
		add_action( 'admin_init', array($this, 'admin_remove_user') );
		
		// filtros
		$this->form_model = apply_filters( 'boros_newsletter_form_model', $this->form_model );
		$this->form_options = apply_filters( 'boros_newsletter_form_options', $this->form_options );
		
		$this->register_forms(); //pre($this->forms, 'forms');
		$this->proccess_data();
	}
	
	private function dependecy_check(){
		$this->boros_active = plugin_is_active('boros/boros.php') ? true : false;
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
	 * Os modelos de formulário são registrados em $forms, permitindo que sejam usados mais que um modelo em um mesmo site.
	 * 
	 */
	private function register_forms(){
		$this->forms = apply_filters( 'boros_newsletter_register_forms', $this->forms );
	}
	
	private function get_form_config( $form ){
		$form['form_options'] = boros_parse_args( $this->form_options, $form['form_options'] );
		$form['form_attrs'] = boros_parse_args( $this->form_attrs, $form['form_attrs'] );
		$form['form_messages'] = boros_parse_args( $this->form_messages, $form['form_messages'] );
		return $form;
	}
	
	/**
	 * Processar os dados em caso de $_POST
	 * 
	 */
	private function proccess_data(){
		if( isset($_POST['form_type']) and $_POST['form_type'] == 'boros_newsletter_form' ){
			if( isset($_POST['form_name']) and isset($this->forms[$_POST['form_name']]) ){
				// configuração do form
				$form = $this->get_form_config( $this->forms[$_POST['form_name']] );
				
				return false;
				/**
				 * Preparar os dados
				 * 
				 */
				foreach( $_POST as $key => $value ){
					if( isset($form_model[$key]) ){
						// sanitize
						$san_data = sanitize_form_input($value);
						
						// label do campo
						$label = $form_model[$key]['label'];
						
						// obrigatório e pegou vazio
						if( ($form_model[$key]['required'] == true) and empty($san_data) ){
							$form_errors[$key] = "O campo {$label} precisa ser preenchido.";
						}
						// preenchido, validar
						elseif( !empty($san_data) ){
							
							// preenchido, porém é valor padrão e não aceito
							if( ($form_model[$key]['accept_std'] == false) and ($san_data == $form_model[$key]['std']) ){
								$form_errors[$key] = "O campo {$label} precisa ser preenchido corretamente.";
							}
							else{
								switch( $form_model[$key]['validate'] ){
									case 'string':
										// OK!
										if( filter_var( $san_data, FILTER_SANITIZE_STRING) ){
											$form_data[$key] = $san_data;
										}
										// ERROR!
										else{
											$form_errors[$key] = "{$label} não é string válida : {$san_data}.";
										}
										break;
									case 'email':
										// OK!
										$san_email = filter_var($san_data, FILTER_SANITIZE_EMAIL);
										if( filter_var( $san_email, FILTER_VALIDATE_EMAIL) ){
											// verificar se já existe na base
											global $wpdb;
											$email = $wpdb->get_row("SELECT person_email FROM {$wpdb->prefix}newsletter WHERE person_email = '{$san_email}'");
											if( $email )
												$form_errors[$key] = "E-mail já cadastrado.";
											else
												$form_data[$key] = $san_email;
										}
										// ERROR!
										else{
											$form_errors[$key] = "{$label} não é email válido : {$san_email}.";
										}
										break;
									case 'bool':
										// OK!
										if( filter_var( $san_data, FILTER_VALIDATE_BOOLEAN) ){
											$form_data[$key] = $san_data;
										}
										// ERROR!
										else{
											$form_errors[$key] = "{$label} não é um inteiro válido : {$san_data}.";
										}
										break;
									case 'estado':
										$estados = array('Acre','Alagoas','Amapá','Amazonas','Bahia','Ceará','Distrito Federal','Espírito Santo', 'Goiás', 'Maranhão','Mato Grosso','Mato Grosso do Sul','Minas Gerais', 'Pará','Paraíba', 'Paraná','Pernambuco', 'Piauí','Rio de Janeiro','Rio Grande do Norte','Rio Grande do Sul','Rondônia','Roraima','Santa Catarina','São Paulo','Sergipe','Tocantins');
										// OK!
										if( in_array( $san_data, $estados ) ){
											$form_data[$key] = $san_data;
										}
										// ERROR!
										else{
											$form_errors[$key] = "{$san_data} não é um estado válido.";
											$form_data[$key] = $form_model[$key]['std'];
										}
										
										break;
								}
							}
						}
						// opcional e vazio
						else{
							$form_data[$key] = $san_data;
						}
					} //if input exists
					//pre($form_model, 'form_model');
					//pre($form_data, 'form_data');
					//pre($form_errors, 'form_errors');
				} //foreach
				
				
				/**
				 * Se não tiver errors, gravar dados e exibir mensagem
				 * 
				 * Monta o array de gravação, conforme as colunas ( 'coluna' => 'valor' )
				 * Para a coluna 'person_metadata' é criado um array para a gravação do array serializado
				 * 
				 */
				//pre($form_data, 'form_data');
				//pre($form_errors, 'form_errors');
				/**/
				if( empty($form_errors) and !empty($form_data) ){
					$data_array = array();
					
					foreach( $form_data as $name => $value ){
						$column = $form_model[$name]['db_column'];
						switch( $column ){
							case 'person_name':
							case 'person_email':
								$data_array[ $column ] = $value;
								break;
							
							// adicionar quantos metadados existirem no modelo
							case 'person_metadata':
								$data_array[ $column ][$name] = $value;
								break;
						}
					}
					
					// adicionar datano formato sql com o ajuste de horário
					date_default_timezone_set('America/Sao_Paulo');
					$data_array[ 'person_date' ] = date("Y-m-d H:i:s");
					
					if( isset($data_array['person_metadata']) and !is_serialized( $data_array['person_metadata'] ) ){
						$data_array['person_metadata'] = maybe_serialize($data_array['person_metadata']);
					}
					
					//pre($data_array, 'data_array');
					$wpdb->insert( $wpdb->prefix . 'newsletter', $data_array );
					$form_msg = '<div class="form_msg"><span class="success">Cadastrado com sucesso.</span></div>';
					$this->form_status = 'success';
				}
				else{
					$this->form_status = 'error';
				}
			}
		}
	}
	
	/**
	 * O campo hidden 'form_id' será usado com o 'form_name' para identificar qual o form correto dentro de múltiplos em
	 * uma mesma página, para que seja exibido as mensagens de erro no form correto.
	 * 
	 */
	function frontend_output( $form_name ){
		if( isset($this->forms[$form_name]) ){
			// configuração do form
			$form = $this->get_form_config( $this->forms[$form_name] );
			//pre($form, '$form', false);
			
			// definir id única para a página, que pode conter diversas instâncias do mesmo form
			if( !isset($this->forms[$form_name]['count']) ){
				$this->forms[$form_name]['count'] = 1;
			}
			else{
				$this->forms[$form_name]['count']++;
			}
			$form_id = "{$form['form_attrs']['id']}-{$this->forms[$form_name]['count']}";
			
			$classes_arr = array(
				$form['form_attrs']['class'],
				"form_status_{$this->form_status}",
			);
			$classes_arr[] = ($form['form_options']['layout'] == 'normal') ? 'form-normal' : 'form-inline';
			$classes = implode(' ', $classes_arr);
			
			$action = self_url();
			echo "<form action='{$action}#{$form_id}' method='post' id='{$form_id}' class='{$classes}' role='form'>";
			echo "<input type='hidden' name='form_type' value='boros_newsletter_form' />";
			echo "<input type='hidden' name='form_name' value='{$form_name}' />";
			echo $form['form_options']['prepend'];
			
			// exibir mensagens pré-campos
			if( $this->form_status == 'error' ){
				echo $form['form_messages']['error'];
			}
			elseif( $this->form_status == 'success' ){
				echo $form['form_messages']['success'];
			}
			else{
				echo $form['form_messages']['blank'];
			}
			
			// exibir campos
			foreach( $form['form_model'] as $key => $input ){
				$input['name'] = $key;
				$input['id'] = "{$key}-{$this->forms[$form_name]['count']}";
				$input['layout'] = $form['form_options']['layout'];
				$input['form_name'] = $form_name;
				$this->show_input($input);
			}
			
			echo $form['form_options']['append'];
			echo "</form>";
		}
		else {
			echo "Este formulário (id:{$form_name}) não está registrado. É preciso registrar cada form no hook 'boros_newsletter_register_forms'";
		}
	}
	
	private function show_input( $input ){
		if( method_exists($this, "input_type_{$input['type']}") ){
			call_user_func( array($this, "input_type_{$input['type']}"), $input );
		}
		else{
			$this->custom_input_type( $input );
		}
	}
	
	private function input_type_text( $input ){
		$type = ( $input['db_column'] == 'person_email' ) ? 'email' : 'text';
		$size = ( isset($input['size']) ) ? "input-{$input['size']}" : '';
		if( $input['layout'] == 'inline' ){
			$html = "<input type='{$type}' name='{$input['name']}' class='form-control {$input['classes']['input_class']} {$size}' id='{$input['id']}' placeholder='{$input['placeholder']}'>";
			echo "<div class='form-group {$input['classes']['form_group_class']}'>";
			echo "<label class='sr-only {$input['classes']['label_class']}' for='{$input['id']}'>Email address</label>";
			$this->input_addon($html, $input);
			echo "</div>\n";
		}
		elseif( $input['layout'] == 'normal' ){
			
		}
		elseif( $input['layout'] == 'horizontal' ){
			
		}
		else{
			$this->custom_input_layout( $input );
		}
	}
	
	/**
	 * Adicionar add-ons caso tenham sido configurados
	 * 
	 */
	private function input_addon( $html, $input ){
		if( isset($input['addon']) ){
			echo '<div class="input-group">';
			if( isset($input['addon']['before']) ){echo "<span class='input-group-addon'>{$input['addon']['before']}</span>";}
			echo $html;
			if( isset($input['addon']['after']) ){echo "<span class='input-group-addon'>{$input['addon']['after']}</span>";}
			echo "</div>\n";
		}
		elseif( isset($input['append_submit']) and $input['append_submit'] == true ){
			$size = ( isset($input['size']) ) ? "input-group-{$input['size']}" : '';
			echo "<div class='input-group {$size}'>";
			echo $html;
			echo "<span class='input-group-btn {$input['append_submit']['input_group_class']}'><button type='submit' class='btn {$input['append_submit']['input_class']}'>{$input['append_submit']['label']}</button></span>";
			echo "</div>\n";
		}
		else{
			echo $html;
		}
	}
	
	private function input_type_submit( $input ){
		$size = ( isset($input['size']) ) ? "btn-{$input['size']}" : '';
		echo "<input type='submit' class='btn {$input['classes']['input_class']} {$size}' id='{$input['id']}' value='{$input['label']}' />";
	}
	
	private function custom_input_layout( $input ){
		
	}
	
	private function custom_input_type( $input ){
		pre( $input, 'custom_input_type', false );
		$html = '';
		echo apply_filters( "boros_newsletter_custom_input_{$input['type']}", $html, $input );
	}
	
	/**
	 * Remover usuário pelo link de (-) na listagem de usuários no admin
	 * @todo adicionar nonce
	 * 
	 */
	function admin_remove_user(){
		if( isset($_GET['newsletter_action']) and $_GET['newsletter_action'] == 'remove' ){
			global $wpdb;
			$person_id = (int)$_GET['person_id'];
			$wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->newsletter} WHERE person_id = %d", $person_id ) );
		}
	}
	
	/**
	 * Página do admin, registrar
	 * 
	 */
	function admin_page(){
		$admin_page = add_menu_page( 'Newsletter', 'Newsletter', 'edit_pages', 'newsletter_controls' , array($this, 'admin_page_output'), 'dashicons-email' );
	}
	
	/**
	 * Página do admin, output
	 * 
	 */
	function admin_page_output(){
		if( !is_admin() ){ return false; }
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




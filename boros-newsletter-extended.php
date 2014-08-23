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
//add_action( 'wp_footer', 'newsletter_test' );
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
	 * Modelo padrão de configuração
	 * 
	 */
	var $defaults = array(
		'form_options' => array(
			'type' => 'normal',
			'layout' => 'bootstrap3',
			'before' => '',
			'prepend' => '',
			'append' => '',
			'after' => '',
		),
		'form_attrs' => array(
			'id' => 'form-newsletter',
			'class' => 'form-newsletter',
		),
		'form_messages' => array(
			'error' => 'Ocorreram alguns erros, por favor verifique.',
			'success' => 'Formulário enviado com sucesso!',
			'blank' => '',
			'layout' => 'bootstrap3',
			'position' => 'before',
			'show_errors' => true,
			'show_all_errors' => false,
			'show_close_button' => false,
		),
		'form_model' => array(
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
			'ipt_sobrenome' => array(
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
		)
	);
	
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
		
		// continuar apenas caso exista algum form registrado
		$custom_config = apply_filters( 'boros_newsletter_set_config', array() );
		if( !empty($custom_config) ){
			$this->set_form_config( $custom_config );
			$this->proccess_data();
		}
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
	 * Mesclar a configuração enviada com os valores padrões.
	 * 
	 */
	private function set_form_config( $custom_config ){
		//pre( $custom_config, '$custom_config', false );
		foreach( $custom_config as $form_name => $form ){
			// aplicar valores padrão
			$this->forms[$form_name]                  = boros_parse_args( $this->defaults, $form );
			$this->forms[$form_name]['form_id']       = false; // form postado, por padrão é falso, já que pode ser único na página
			$this->forms[$form_name]['form_data']     = array();
			$this->forms[$form_name]['form_errors']   = array();
			$this->forms[$form_name]['form_status']   = 'blank';
			
			// adicionar valores aos inputs
			foreach( $this->forms[$form_name]['form_model'] as $key => $input ){
				$this->forms[$form_name]['form_model'][$key]['name']      = $key;
				$this->forms[$form_name]['form_model'][$key]['form_type'] = $this->forms[$form_name]['form_options']['type'];
				$this->forms[$form_name]['form_model'][$key]['layout']    = $this->forms[$form_name]['form_options']['layout'];
				$this->forms[$form_name]['form_model'][$key]['form_name'] = $form_name;
				$this->forms[$form_name]['form_model'][$key]['error']     = '';
				$this->forms[$form_name]['form_model'][$key]['value']     = '';
			}
		}
		//pre($this->forms, 'forms', false);
	}
	
	/**
	 * Processar os dados em caso de $_POST
	 * 
	 */
	private function proccess_data(){
		if( isset($_POST['form_type']) and $_POST['form_type'] == 'boros_newsletter_form' ){
			if( isset($_POST['form_name']) and isset($this->forms[$_POST['form_name']]) ){
				$form_name = $_POST['form_name'];
				//pre($_POST, '$_POST');
				
				/**
				 * Guardar a id html do form postado, incialmente ele é vazio, e para cada instância é criada uma id
				 * única para cada form, enviada via input:hidden['form_id']
				 * 
				 */
				$this->forms[$form_name]['form_id'] = $_POST['form_id'];
				
				/**
				 * Preparar os dados
				 * 
				 */
				foreach( $this->forms[$form_name]['form_model'] as $key => $input ){
					if( $input['required'] == true and (!isset($_POST[$key]) or empty($_POST[$key])) and $input['db_column'] != 'skip' ){
						$this->set_error( $form_name, $key, "O campo {$input['label']} precisa ser preenchido." );
						$this->set_value( $form_name, $key, $input['std']);
					}
					else{
						$value = sanitize_text_field($_POST[$key]);
						
						// preenchido, porém é valor padrão e não aceito
						if( ($input['accept_std'] == false) and ($value == $input['std']) and $input['db_column'] != 'skip' ){
							$this->set_error( $form_name, $key, "O campo {$input['label']} precisa ser preenchido corretamente." );
						}
						else{
							switch( $input['validate'] ){
								case 'string':
									$value = filter_var( $value, FILTER_SANITIZE_STRING);
									if( $value == true ){
										$this->set_value( $form_name, $key, $value);
									}
									else{
										$this->set_error( $form_name, $key, "{$label} não é string válida." );
										$this->set_value( $form_name, $key, $input['std']);
									}
									break;
								
								case 'email':
									$value = filter_var($value, FILTER_SANITIZE_EMAIL);
									if( filter_var( $value, FILTER_VALIDATE_EMAIL) ){
										// verificar se já existe na base
										global $wpdb;
										$email = $wpdb->get_row("SELECT person_email FROM {$wpdb->prefix}newsletter WHERE person_email = '{$value}'");
										if( $email ){
											$this->set_error( $form_name, $key, "E-mail já cadastrado." );
										}
									}
									else{
										$this->set_error( $form_name, $key, "{$label} não é email válido : {$value}." );
									}
									$this->set_value( $form_name, $key, $value);
									break;
								
								case 'bool':
									if( filter_var( $value, FILTER_VALIDATE_BOOLEAN) ){
										$this->set_value( $form_name, $key, $value);
									}
									else{
										$this->set_error( $form_name, $key, "{$label} não é um inteiro válido" );
										$this->set_value( $form_name, $key, $input['std']);
									}
									break;
								
								case 'estado':
									$estados = array('Acre','Alagoas','Amapá','Amazonas','Bahia','Ceará','Distrito Federal','Espírito Santo', 'Goiás', 'Maranhão','Mato Grosso','Mato Grosso do Sul','Minas Gerais', 'Pará','Paraíba', 'Paraná','Pernambuco', 'Piauí','Rio de Janeiro','Rio Grande do Norte','Rio Grande do Sul','Rondônia','Roraima','Santa Catarina','São Paulo','Sergipe','Tocantins');
									if( in_array( $value, $estados ) ){
										$this->set_value( $form_name, $key, $value);
									}
									else{
										$this->set_error( $form_name, $key, "{$value} não é um estado válido." );
										$this->set_value( $form_name, $key, $input['std']);
									}
									break;
							}
						}
					}
					//pre($this->forms[$form_name]['form_model'][$key], $key);
				}
				
				/**
				 * Preparar e gravar dados
				 * Monta o array de gravação, conforme as colunas ( 'coluna' => 'valor' )
				 * Para a coluna 'person_metadata' é criado um array para a gravação do array serializado
				 * 
				 */
				if( empty($this->forms[$form_name]['form_errors']) and !empty($this->forms[$form_name]['form_data']) ){
					$data_array = array();
					foreach( $this->forms[$form_name]['form_data'] as $key => $value ){
						$column = $this->forms[$form_name]['form_model'][$key]['db_column'];
						switch( $column ){
							case 'person_name':
							case 'person_email':
								$data_array[ $column ] = $value;
								break;
							
							// adicionar quantos metadados existirem no modelo
							case 'person_metadata':
								$data_array[ $column ][$key] = $value;
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
					$this->forms[$form_name]['form_status'] = 'success';
				}
				else{
					$this->forms[$form_name]['form_status'] = 'error';
				}
				
				//pre($this->forms[$form_name], 'form POS');
				//return false;
			}
		}
	}
	
	/**
	 * Registrar erros no input e no form
	 * 
	 */
	private function set_error( $form_name, $key, $error ){
		$this->forms[$form_name]['form_model'][$key]['error'] = $error;
		$this->forms[$form_name]['form_errors'][$key] = $error;
	}
	
	/**
	 * Registrar dados válidos no input e no form
	 * 
	 */
	private function set_value( $form_name, $key, $value ){
		if( $this->forms[$form_name]['form_model'][$key]['db_column'] != 'skip' ){
			$this->forms[$form_name]['form_model'][$key]['value'] = $value;
			$this->forms[$form_name]['form_data'][$key] = $value;
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
			$form = $this->forms[$form_name];
			//pre($form, '$form', true);
			
			// definir id única para a página, que pode conter diversas instâncias do mesmo form
			if( !isset($this->forms[$form_name]['count']) ){
				$this->forms[$form_name]['count'] = 1;
			}
			else{
				$this->forms[$form_name]['count']++;
			}
			// definir a id para esta instância na página, pode haver múltplas na mesma página
			$form_id = "{$form['form_attrs']['id']}-{$this->forms[$form_name]['count']}";
			
			$classes_arr = array(
				$form['form_attrs']['class'],
			);
			$classes_arr[] = ($form['form_options']['type'] == 'normal') ? 'form-normal' : 'form-inline';
			
			// modificações para o form postado
			if( $form_id == $form['form_id'] ){
				$classes_arr[] = "form-status-{$form['form_status']}";
			}
			$classes = implode(' ', $classes_arr);
			
			// mensagens apenas para o form postada
			$messages_html = '';
			if( $form_id == $form['form_id'] ){
				$btn = ( $form['form_messages']['show_close_button'] == true ) ? '<button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>' : '';
				if( $form['form_status'] == 'error' and $form['form_messages']['show_errors'] == true ){
					if( $form['form_messages']['layout'] == 'bootstrap3' ){
						$message = $form['form_messages']['error'];
						if( $form['form_messages']['show_all_errors'] == true ){
							foreach( $form['form_errors'] as $key => $error ){
								$message .= "<p>{$error}</p>";
							}
						}
						$messages_html .= "<div class='alert alert-danger' role='alert'>{$btn}{$message}</div>";
					}
					else{
						$messages_html .= apply_filters( "boros_newsletter_{$form_name}_form_message", $form['form_messages']['error'], 'error' );
					}
				}
				elseif( $form['form_status'] == 'success' ){
					if( $form['form_messages']['layout'] == 'bootstrap3' ){
						$messages_html .= "<div class='alert alert-success' role='alert'>{$btn}{$form['form_messages']['success']}</div>";
					}
					else{
						$messages_html .= apply_filters( "boros_newsletter_{$form_name}_form_message", $form['form_messages']['success'], 'success' );
					}
				}
				else{
					$messages_html .= $form['form_messages']['blank'];
				}
			}
			else{
				$messages_html .= $form['form_messages']['blank'];
			}
			
			$action = self_url();
			echo $form['form_options']['before'];
			echo "<form action='{$action}#{$form_id}' method='post' id='{$form_id}' class='{$classes}' role='form'>";
			echo "<input type='hidden' name='form_type' value='boros_newsletter_form' />";
			echo "<input type='hidden' name='form_name' value='{$form_name}' />";
			echo "<input type='hidden' name='form_id' value='{$form_id}' />";
			echo $form['form_options']['prepend'];
			if( $form['form_messages']['position'] == 'before' ){ echo $messages_html; }
			
			// exibir campos
			foreach( $form['form_model'] as $key => $input ){
				// adicionar ID único por <form>
				$input['id'] = "{$form_id}-{$key}";
				// adicionar class de status de validação
				if( !empty($input['error']) and $form_id == $form['form_id'] ){
					$input['classes']['form_group_class'] .= ' has-error';
				}
				$this->show_input($input);
			}
			if( $form['form_messages']['position'] == 'after' ){ echo $messages_html; }
			echo $form['form_options']['append'];
			echo "</form>";
			echo $form['form_options']['after'];
		}
		else {
			echo "<div class='alert alert-danger' role='alert'>Este formulário (id: <strong>{$form_name}</strong>) não está registrado. É preciso registrar cada form no hook <code>boros_newsletter_register_forms</code></div>";
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
		if( $input['form_type'] == 'inline' ){
			$value = $this->input_reload($input);
			$html = "<input type='{$type}' name='{$input['name']}' class='form-control {$input['classes']['input_class']} {$size}' id='{$input['id']}' placeholder='{$input['placeholder']}' value='{$value}'>";
			echo "<div class='form-group {$input['classes']['form_group_class']}'>";
			if( $input['label'] != false ){ echo "<label class='sr-only {$input['classes']['label_class']}' for='{$input['id']}'>{$input['label']}</label>"; }
			$this->input_addon($html, $input);
			echo "</div>\n";
		}
		elseif( $input['form_type'] == 'normal' ){
			
		}
		elseif( $input['form_type'] == 'horizontal' ){
			
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
		$addon = boros_trim_array($input['addon']);
		if( !empty($addon) ){
			echo '<div class="input-group">';
			if( !empty($input['addon']['before']) ){echo "<span class='input-group-addon'>{$input['addon']['before']}</span>";}
			echo $html;
			if( !empty($input['addon']['after']) ){echo "<span class='input-group-addon'>{$input['addon']['after']}</span>";}
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
	
	private function input_reload( $input ){
		if( empty($input['value']) ){
			return $input['std'];
		}
		else{
			return $input['value'];
		}
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
				
				$columns = $this->get_admin_page_columns();
				//$metadatas = array();
				//$metadata_th = '';
				//foreach( $this->form_model as $item => $attr ){
				//	if( $attr['db_column'] == 'person_metadata' and !in_array( $item, $exclude_metadata_column ) ){
				//		$metadatas[] = $item;
				//		$metadata_th .= "<th>{$attr['label']}</th>";
				//	}
				//}
			?>
			<h3>Dados cadastrados:</h3>
			
			<table id="newsletter_data" class="widefat">
				<thead>
					<tr>
						<th class="check-column"></th>
						<?php foreach( $columns as $key => $col ){
							echo "<th>{$col['label']}</th>";
						}
						?>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th class="check-column"></th>
						<?php foreach( $columns as $key => $col ){
							echo "<th>{$col['label']}</th>";
						}
						?>
					</tr>
				</tfoot>
			<?php
			add_filter( 'boros_newsletter_admin_page_input_person_date', array($this, 'date_filter') );
			$i = 0;
			foreach( $cadastros as $cad ){
				$args = array(
					'newsletter_action' => 'remove',
					'person_id' => $cad['person_id'],
				);
				$tr_class = ($c++%2==1) ? '' : 'alternate';
			?>
				<tr class="<?php echo $tr_class; ?>">
					<td><a href="<?php echo add_query_arg( $args ); ?>" class="newsletter_remove_btn" title="ID: <?php echo $cad['person_id']; ?>">Remover</a></td>
					<?php
					$metadatas = maybe_unserialize($cad['person_metadata']);
					foreach( $columns as $key => $col ){
						if( $col['type'] == 'metadata' ){
							$val = '';
							if( isset($metadatas[$key]) ){
								$val = apply_filters( "boros_newsletter_admin_page_input_{$key}", $metadatas[$key] );
							}
							echo "<td>{$val}</td>";
						}
						else{
							$val = apply_filters( "boros_newsletter_admin_page_input_{$key}", $cad[$key] );
							echo "<td>{$val}</td>";
						}
					}
					?>
				</tr>
				<?php
				$i++;
			}
			?>
			</table>
			<div class="tablenav form-table" style="height:auto;">
				<form action="<?php echo get_bloginfo('wpurl') . '/wp-admin/admin.php'; ?>" method="get">
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
	 * Montar as colunas do admin, mesclando metadatas de múltiplos forms
	 * 
	 */
	private function get_admin_page_columns(){
		$columns = array(
			'person_email' => array(
				'label' => 'E-mail', 
				'type' => 'column',
			), 
			'person_name' => array(
				'label' => 'Nome', 
				'type' => 'column',
			), 
			'ipt_sobrenome' => array(
				'label' => 'Sobrenome', 
				'type' => 'metadata',
			), 
			'person_date' => array(
				'label' => 'Data', 
				'type' => 'column',
			), 
		);
		return apply_filters( 'boros_newsletter_admin_page_columns', $columns );
	}
	
	function date_filter( $date ){
		$new_date = $date != '0000-00-00 00:00:00' ? mysql2date('d\/m\/Y \à\s h:m:s', $date) : 'Sem data';
		return $new_date;
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




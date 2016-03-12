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
 * - poder configurar mensagens de erro para cada tipo de validação, e não apenas para cada input
 * - adicionar campo de opções, para usar uma única tabela em multisite. Atualmente a opção está em admin_page do plugin do job. Verificar a adição de adicionar como opção da rede.
 * - integrar akismet
 * - criar os outputs dos outros tipos de forms, assim como de outros tipos de campos como select, checkbox e radio
 * - adicionar nonces, para dificultar spam
 * - deixar modelos criados de form inline, horizontal e normal(um embaixo do outro)
 * - campos condicionais
 * - AJAX FRONTEND >> submit com mensagens de sucesso ou erro + form de novo
 * - AJAX ADMIN >>> remover/editar entrada
 * - Integrar o js no plugin
 * - Verificar as datas pedidas para download, validar período
 * - Página de configuração inicial: escolher nome e criar tabela
 * 
 * 
 * @link http://www.tutorialrepublic.com/twitter-bootstrap-tutorial/bootstrap-forms.php
 * @link http://getbootstrap.com/css/#forms
 * 
 */



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
	static $version = '1.0';
	
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
				'size' => '',
				'append_submit' => false,
			),
			/**
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
				'size' => '',
				'append_submit' => false,
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
				'size' => 'sm',
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
				'size' => '',
			),
			/**/
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
		load_plugin_textdomain( 'boros-newsletter-extended', false, basename( dirname( __FILE__ ) ) . '/languages' );
		
		// verificar dependência
		$this->dependecy_check();
		
		// registrar tabela no $wpdb
		$this->init_table();
		
		// actions
		add_action( 'admin_menu', array($this, 'admin_page') );
		add_action( 'admin_init', array($this, 'admin_remove_user') );
		add_action( 'wp_ajax_boros_newsletter_download', array($this, 'boros_newsletter_download') ); // admin
		
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
		$wpdb->$table_name = self::table_name();
	}
	
	/**
	 * Mesclar a configuração enviada com os valores padrões.
	 * 
	 * @todo criar uma forma de adicionar o submit caso não tenha sido declarado e/ou quando nenhum dos elementos foi definido
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
		$new_table_name = self::table_name();
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
				 * Verificar spam
				 * Na configuração do form, é possível escolher quais campos representam os valores que serão passados 
				 * para o Akismet.
				 * 
				 */
				$is_spam = false;
				if( isset($this->forms[$form_name]['form_options']['akismet_fields']) ){
					$akismet_fields = array(
						'author'  => '',
						'email'   => '',
						'url'     => '',
						'comment' => '',
					);
					foreach( $this->forms[$form_name]['form_options']['akismet_fields'] as $aksmt_key => $news_key ){
						if( isset($_POST[$news_key]) ){
							$akismet_fields[$aksmt_key] = $_POST[$news_key];
						}
					}
					$is_spam = $this->check_spam( $form_name, $akismet_fields );
					if( $is_spam == true ){
						$this->set_error( $form_name, 'ipt_email', __('Your e-mail is identified as spam.', 'boros-newsletter-extended') );
					}
				}
				
				/**
				 * Preparar os dados
				 * 
				 */
				// pular demais verificações caso seja identificado como spam.
				if( $is_spam == false ){
					foreach( $this->forms[$form_name]['form_model'] as $key => $input ){
                        // pular caso seja submit
                        if( $input['type'] == 'submit' ){
                            continue;
                        }
                        
						if( $input['required'] == true and (!isset($_POST[$key]) or empty($_POST[$key])) and $input['db_column'] != 'skip' ){
							$this->set_error( $form_name, $key, sprintf(__('The %s field needs to be filled.', 'boros-newsletter-extended'), $input['label']) );
							$this->set_value( $form_name, $key, $input['std']);
						}
						else{
							$value = sanitize_text_field($_POST[$key]);
							
							// preenchido, porém é valor padrão e não aceito
							if( ($input['accept_std'] == false) and ($value == $input['std']) and $input['db_column'] != 'skip' ){
								$this->set_error( $form_name, $key, sprintf(__('The %s field must be filled in correctly.', 'boros-newsletter-extended'), $input['label']) );
							}
							else{
								switch( $input['validate'] ){
									case 'string':
										$value = filter_var( $value, FILTER_SANITIZE_STRING);
										if( $value == true ){
											$this->set_value( $form_name, $key, $value);
										}
										else{
											$this->set_error( $form_name, $key, __('This is not valid input.', 'boros-newsletter-extended') );
											$this->set_value( $form_name, $key, $input['std']);
										}
										break;
									
									case 'email':
										$value = filter_var($value, FILTER_SANITIZE_EMAIL);
										if( filter_var( $value, FILTER_VALIDATE_EMAIL) ){
											// verificar se já existe na base
											global $wpdb;
											$email = $wpdb->get_row("SELECT person_email FROM {$new_table_name} WHERE person_email = '{$value}'");
											if( $email ){
												$this->set_error( $form_name, $key, __('Your e-mail is already in our mailing list.', 'boros-newsletter-extended') );
											}
										}
										else{
											$this->set_error( $form_name, $key, __('This is not a valid email address.', 'boros-newsletter-extended') );
										}
										$this->set_value( $form_name, $key, $value);
										break;
									
									case 'bool':
										if( filter_var( $value, FILTER_VALIDATE_BOOLEAN) ){
											$this->set_value( $form_name, $key, $value);
										}
										else{
											$this->set_error( $form_name, $key, __('This is not a valid integer.', 'boros-newsletter-extended') );
											$this->set_value( $form_name, $key, $input['std']);
										}
										break;
									
									case 'estado':
										$estados = array('Acre','Alagoas','Amapá','Amazonas','Bahia','Ceará','Distrito Federal','Espírito Santo', 'Goiás', 'Maranhão','Mato Grosso','Mato Grosso do Sul','Minas Gerais', 'Pará','Paraíba', 'Paraná','Pernambuco', 'Piauí','Rio de Janeiro','Rio Grande do Norte','Rio Grande do Sul','Rondônia','Roraima','Santa Catarina','São Paulo','Sergipe','Tocantins');
										if( in_array( $value, $estados ) ){
											$this->set_value( $form_name, $key, $value);
										}
										else{
											$this->set_error( $form_name, $key, __('This is not a valid state.', 'boros-newsletter-extended') );
											$this->set_value( $form_name, $key, $input['std']);
										}
										break;
								}
							}
						}
						//pre($this->forms[$form_name]['form_model'][$key], $key);
					}
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
					// adicionar data no formato sql com o ajuste de horário
					date_default_timezone_set('America/Sao_Paulo');
					$data_array[ 'person_date' ] = date("Y-m-d H:i:s");
					
					if( isset($data_array['person_metadata']) and !is_serialized( $data_array['person_metadata'] ) ){
						$data_array['person_metadata'] = maybe_serialize($data_array['person_metadata']);
					}
					
					//pre($data_array, 'data_array');
					$wpdb->insert( $new_table_name, $data_array );
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
	 * Registrar erros no input e no form.
	 * Será exibida a mensagem de erro configurada para o campo, caso contrário será usado o padrão enviado.
	 * 
	 */
	private function set_error( $form_name, $key, $default_message ){
		$message = isset($this->forms[$form_name]['form_model'][$key]['error_message']) ? $this->forms[$form_name]['form_model'][$key]['error_message'] : $default_message;
		$this->forms[$form_name]['form_model'][$key]['error'] = $message;
		$this->forms[$form_name]['form_errors'][$key] = $message;
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
	
	private function check_spam( $form_name, $data ){
		require_once 'Akismet.class.php';
		
		$home_url = home_url('/');
		$akismet_key = $this->forms[$form_name]['form_options']['akismet_key'];
		$akismet = new AkismetValidation( $home_url, $akismet_key);
		if( $akismet->isKeyValid() ){
			//$akismet->setCommentAuthor('viagra-test-123'); // test positive spam
			$akismet->setCommentAuthor($data['author']);
			$akismet->setCommentAuthorEmail($data['email']);
			$akismet->setCommentAuthorURL($data['url']);
			$akismet->setCommentContent($data['comment']);
			$akismet->setPermalink( $home_url );
			return $akismet->isCommentSpam();
		}
		else{
			$alerts = get_option('boros_dashboard_notifications');
			if( !isset($alerts['akismet_key_error']) ){
				$alerts['akismet_key_error'] = 'A chave API do Akismet está vazia ou é incorreta';
				update_option('boros_dashboard_notifications', $alerts);
			}
		}
		//pre($akismet, '$akismet');
		return false;
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
		//pre( $input, 'custom_input_type', false );
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
	 * Adicionar diretamente usuário novo ou atualizar dados caso já exista. Será usado como callbacks de outras functions.
	 * Na primeira versão existe um bloco que remove o usuário da lista caso não tenha sido preenchido metadadtas. Verificar
	 * se ainda existe a necessidade disso.
	 * 
	 */
	function append_user( $data_array ){
		// verificar se já existe na base
		global $wpdb;
		$new_table_name = self::table_name();
		$person = $wpdb->get_row("SELECT * FROM {$new_table_name} WHERE person_email = '{$data_array['person_email']}'");
		$table_name = self::table_name();
		
		// atualizar dados caso a pessoa já exista
		if( $person ){
			$oldmeta = maybe_unserialize( $person->person_metadata );
			
			// verificar se é igual, e atualizar
			if( $data_array['person_metadata'] != $oldmeta ){
				if( !empty($oldmeta) )
					$data_array['person_metadata'] = maybe_serialize($data_array['person_metadata'] + $oldmeta);
				
				if( !is_serialized( $data_array['person_metadata'] ) ){
					$data_array['person_metadata'] = maybe_serialize($data_array['person_metadata']);
				}
				
				if( !empty($data_array['person_metadata']) ){
					$where = array(
						'person_email' => $data_array['person_email']
					);
					$wpdb->update( $new_table_name, $data_array, $where );
				}
			}
		}
		// adicionar novo registro
		else{
			if( !empty($data_array['person_metadata']) ){
				if( !is_serialized( $data_array['person_metadata'] ) ){
					$data_array['person_metadata'] = maybe_serialize($data_array['person_metadata']);
				}
				$wpdb->insert( $table_name, $data_array );
			}
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
			
			<form id="export_data" action="<?php echo admin_url('admin-ajax.php'); ?>" method="post">
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
				<input type="hidden" name="action" value="boros_newsletter_download" />
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
				$tabela = self::table_name();
				
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
				$tr_class = ($i++%2==1) ? '' : 'alternate';
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
	
	private function get_download_columns(){
		$columns = array(
			'person_id' => array(
				'label' => 'ID', 
				'type' => 'column',
			),
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
				'label' => 'Data formatada', 
				'type' => 'column',
			),
			'person_date_original' => array(
				'label' => 'Data original', 
				'type' => 'column',
			), 
		);
		return apply_filters( 'boros_newsletter_download_columns', $columns );
	}
	
	function date_filter( $date ){
		$new_date = $date != '0000-00-00 00:00:00' ? mysql2date('d\/m\/Y \à\s h:m:s', $date) : 'Sem data';
		return $new_date;
	}
	
	function boros_newsletter_download(){
		if( empty($this->forms) ){
			die('sem dados para exportar');
		}
		else{
			// load excel library
			require_once 'php-excel.class.php';

			extract( $_POST );
			$start_date = "{$start_ano}-{$start_mes}-{$start_dia}";
			$end_date = "{$end_ano}-{$end_mes}-{$end_dia}";

			// query dos dados
			global $wpdb, $post;
			$tabela = self::table_name();

			$start_date = "{$start_ano}-{$start_mes}-{$start_dia}";
			$end_date = "{$end_ano}-{$end_mes}-{$end_dia}";
			
			$columns = $this->get_download_columns();
			
			$query = "
				SELECT  *
				FROM {$tabela}
				WHERE person_date >= '{$start_date} 00:00:00'
				AND person_date <= '{$end_date} 23:59:59'
				ORDER BY person_date ASC
			";
			$query = apply_filters( 'boros_newsletter_download_query', $query, $tabela, $start_date, $end_date );
			$cadastros = $wpdb->get_results($query, ARRAY_A);
			
			if( $cadastros ){
				$data = array();
				$headers = array();
				foreach( $columns as $key => $col ){
					$headers[] = $col['label'];
				}
				$data[] = $headers;
			}
			else{
				return false;
				die();
			}
			
			add_filter( 'boros_newsletter_download_input_person_date', array($this, 'date_filter') );
			foreach( $cadastros as $cad ){
				$values = array();
				$metadatas = maybe_unserialize($cad['person_metadata']);
				foreach( $columns as $key => $col ){
					if( $col['type'] == 'metadata' ){
						$val = '';
						if( isset($metadatas[$key]) ){
							$val = apply_filters( "boros_newsletter_download_input_{$key}", $metadatas[$key] );
						}
						$values[] = $val;
					}
					elseif( $key == 'person_date_original' ){
						$values[] = $cad['person_date'];
					}
					else{
						$val = apply_filters( "boros_newsletter_download_input_{$key}", $cad[$key] );
						$values[] = $val;
					}
				}
				$data[] = array_values($values);
			}
			//pre($data); exit();
			
			// generate file (constructor parameters are optional)
			$site_name = get_bloginfo('name', 'display');
			$site_slug = sanitize_title($site_name);
			$xls = new Excel_XML('UTF-8', false, "{$site_name} Emails - {$start_dia}-{$start_mes}-{$start_ano}_{$end_dia}-{$end_mes}-{$end_ano}");
			$xls->addArray($data);
			$xls->generateXML("{$site_slug}_emails_{$start_dia}-{$start_mes}-{$start_ano}_{$end_dia}-{$end_mes}-{$end_ano}");
			
			exit();
		}
		die();
	}
	
	/**
	 * Verificar se tabela de newsletter já existe, e caso contrário criá-la. Utilizado apenas na ativação do plugin.
	 * Possui verificação de multisite.
	 * 
	 * @link http://shibashake.com/wordpress-theme/write-a-plugin-for-wordpress-multi-site
	 */
	static function check_table( $networkwide ){
		global $wpdb;
		
		if( function_exists('is_multisite') && is_multisite() && get_option('boros_newsletter_multisite_single_table') == false ){
			if($networkwide){
				$old_blog = $wpdb->blogid;
				// Get all blog ids
				$blogids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
				foreach( $blogids as $blog_id ){
					switch_to_blog( $blog_id );
					self::create_table();
				}
				switch_to_blog( $old_blog );
				return;
			}   
		} 
		self::create_table();
	}
	
	static function create_table(){
		global $wpdb;
		$new_table_name = self::table_name();
		
		// criar tabela se não existir
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
	
	/**
	 * Definir o nome da tabela para gravar, com opção de escolher uma única no caso de multisite.
	 * 
	 */
	static function table_name(){
		global $wpdb;
		
		if( get_option('boros_newsletter_multisite_single_table') == true ){
			$primary_site_prefix = $wpdb->get_blog_prefix(1);
			return $primary_site_prefix . self::$table_name;
		}
		else{
			return $wpdb->prefix . self::$table_name;
		}
	}
}




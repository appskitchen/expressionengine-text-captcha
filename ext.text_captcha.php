<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/***
 * codeTrio - Text Captcha
 * Extension that replace ExpressionEngine default graphic captcha by logic-based textual questions.
 *
 * @package			Text Captcha
 * @author			codeTrio DevTeam
 * @copyright		Copyright (c) 2012, codeTrio
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @link			http://www.codetrio.com/
 * @version			1.0
 * @filesource 	./system/expressionengine/third_party/text_captcha/ext.text_captcha.php 
 */
class Text_captcha_ext{
    public $name            = 'Text Captcha';
    public $version         = '1.0';
    public $description     = 'Replace ExpressionEngine default graphic captcha by logic-based textual questions by taking  advantage of the TextCaptcha.com service.';
    public $settings_exist  = 'y';
    public $docs_url        = 'http://www.codetrio.com/';
    public $settings        = array();
    public $siteId;
    
    function __construct($settings='') {
        $this->EE=& get_instance();
        $this->siteId = $this->EE->config->item('site_id');
        $this->settings=$settings;
    }
    
    function activate_extension(){
        
        $this->settings[$this->siteId]='demo';
        
        $data['class']= __CLASS__;
        $data['method']= "create_captcha";
        $data['hook']= "create_captcha_start";
        $data['settings']= serialize($this->settings);
        $data['priority']= 10;
        $data['version']= $this->version;
        $data['enabled']= "y";
		
    	$this->EE->db->insert('extensions', $data);
    	
        $data['class']= __CLASS__;
        $data['method']= "postOverWrite";
        $data['hook']= "sessions_end";
        $data['settings']= serialize($this->settings);
        $data['priority']= 1;
        $data['version']= $this->version;
        $data['enabled']= "y";
		
    	$this->EE->db->insert('extensions', $data);
    }
    function update_extension($current = ''){
        $status = TRUE;
        if ($this->version != $current){
            $data = array();
            $data['version'] = $this->version;
            $this->EE->db->update('extensions', $data, 'version = '.$current);

            if($this->EE->db->affected_rows() != 1){
                    $status = FALSE;
            }
        }

        return $status;
    }
    
    function disable_extension(){
        $this->EE->db->where('class', __CLASS__);
    	$this->EE->db->delete('extensions');
    }
    
    function settings_form($settings){
        $form=form_open('C=addons_extensions'.AMP.'M=save_extension_settings'.AMP.'file=text_captcha');
        $this->EE->table->set_template('cp_pad_table_template');
        $this->EE->table->set_heading(
            array('data' => lang('preference'), 'style' => 'width:50%;'),
            lang('setting')
        );
        $apiKey=isset($settings[$this->siteId]) ? $settings[$this->siteId] :'';
        $this->EE->table->add_row(form_label('textCAPTCHA.com API Key', 'api_key'), form_input('api_key', $apiKey));
        $form.=  $this->EE->table->generate();
        $this->EE->table->clear();
        $form.=form_submit(array(
            'name'=>'submit',
            'value'=>'Submit',
            'class'=>'submit'
        ));
        $form.=form_close();
        return $form;
    }
    /*
     * @todo: multiple site manager
     */
    function save_settings(){
        if (empty($_POST)){
            show_error($this->EE->lang->line('unauthorized_access'));
        }
        if(empty ($_POST['api_key'])){
            show_error($this->EE->lang->line('empty_api_key'));
        }
        unset($_POST['submit']);
        $q=$this->EE->db->get_where('extensions', array('class'=> __CLASS__));
        $settings=$q->row_array();
        $settings=unserialize($settings['settings']);
        $settings[$this->siteId]=$_POST['api_key'];
        //print_r($settings);
        //$arr[$this->siteId]=$_POST['api_key'];
        $this->EE->db->where('class', __CLASS__);
        $this->EE->db->update('extensions', array('settings' => serialize($settings)));

        $this->EE->session->set_flashdata(
            'message_success',
            $this->EE->lang->line('preferences_updated')
        );

    }
    
    function create_captcha($old_word=''){
        unset($old_word);
        
        if(!isset($this->settings[$this->siteId]) || $this->settings[$this->siteId]['api_key']==''){
            $this->EE->extensions->end_script = FALSE;
            return false;
        }
        $this->EE->extensions->end_script = TRUE;
        $qAndA=$this->getTextCaptcha();
        $data['date'] = time();
        $data['ip_address'] = $this->EE->input->ip_address();
        foreach($qAndA['answer'] as $answer){
            $data['word']=substr($answer, 0, 19);
            $this->EE->db->insert('captcha', $data);
        }
        return $qAndA['question'];
    }
    
    function getTextCaptcha(){
        
        $url='http://api.textcaptcha.com/'.$this->settings[$this->siteId];
        try {
          $xml = @new SimpleXMLElement($url,null,true);
        } catch (Exception $e) {
          // if there is a problem, use static fallback..
          $fallback = '<captcha>'.
              '<question>Is ice hot or cold?</question>'.
              '<answer>'.md5('cold').'</answer></captcha>';
          $xml = new SimpleXMLElement($fallback);
        }
        // display question as part of form
        $question = (string) $xml->question;

        // store answers in session
        $ans = array();
        foreach ($xml->answer as $hash){
            $ans[] = (string) $hash;
        }
        
        return array(
            'question'=>$question,
            'answer'=>  $ans
        );
    }
    
    function postOverWrite(){
        
        if(!isset($this->settings[$this->siteId]) || $this->settings[$this->siteId]['api_key']==''){
            $this->EE->extensions->end_script = FALSE;
            return false;
        }
        if(isset($_POST['captcha'])){
            $_POST['captcha']=substr(md5(strtolower(trim($_POST['captcha']))), 0, 19);
        }
        
        $this->EE->lang->loadfile('text_captcha');
        $captcha_required = $this->EE->lang->line('captcha_required');
        $captcha_incorrect = $this->EE->lang->line('captcha_incorrect');
        $this->EE->lang->loadfile('core');
		
        // Override the lang.core.php keys for captchas
        $this->EE->lang->language['captcha_required'] = $captcha_required;
        $this->EE->lang->language['captcha_incorrect'] = $captcha_incorrect;
        
        $this->EE->extensions->end_script = false;
    }
}

<?php

/**
 * allinkl_responder
 *
 * Roundcube plugin to manage autoresponders on ALL-INKL servers.
 *
 * @author Michael Wagner
 * @version 1.0
 *
 * Copyright 2015 Michael Wagner
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *     http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */
class allinkl_responder extends rcube_plugin
{
    public $task = 'settings';
    private $kas_result = [];

    function init()
    {
        $this->load_config();
        $this->add_texts('localization/');
        $this->add_hook('settings_actions', [$this, 'settings_actions']);
        $this->register_action('plugin.allinkl_responder_get', [$this, 'init_get_reply']);
        $this->register_action('plugin.allinkl_responder_set', [$this, 'set_reply']);
        $this->register_action('plugin.allinkl_responder_rst', [$this, 'set_reply_rst']);

        $this->include_script('allinkl_responder.js');
    }

    function settings_actions($actions)
    {
        $actions['actions'][] = [
            'action' => 'plugin.allinkl_responder_get',
            'type' => 'link',
            'label' => 'allinkl_responder.replies',
            'title' => 'allinkl_responder.managereplies',
        ];
        return $actions;
    }

    function init_get_reply()
    {
        $this->register_handler('plugin.body', [$this, 'get_reply']);

        $rcmail = rcmail::get_instance();
        $rcmail->output->set_pagetitle($this->gettext('managereplies'));

        $rcmail->output->send('plugin');
    }

    function get_reply()
    {
        $rcmail = rcmail::get_instance();
        $rcmail->output->set_env('product_name', $rcmail->config->get('product_name'));

        $kas_data = $this->get_kas_data();
        $kas_data_item = [];
        if ($kas_data['mail_responder'] == "N") {
            $kas_data_item['from'] = '';
            $kas_data_item['until'] = '';
        } else {
            $kas_data_point = explode('|', $kas_data['mail_responder']);
            $kas_data_item['from'] = date("Y-m-d", $kas_data_point[0]);
            $kas_data_item['until'] = date("Y-m-d", $kas_data_point[1]);
        }

        $table = new html_table(['cols' => 2]);

        // Show current autoresponder entries
        foreach (['from', 'until'] as $value)
        {
            ${'input'.'_'.$value} = new html_inputfield([
                'name' => $value,
                'id' => $value,
                'class' => 'reset',
                'size' => 12,
                'autocomplete' => 'off',
                'autofocus' => true,
                'placeholder' => $this->gettext('datefmt'),
                'value' => $kas_data_item[$value],
            ]);

            $table->add($value, html::label($value, rcube::Q($this->gettext($value))));
            $table->add(null, ${'input'.'_'.$value}->show());
        }

        $input_text = new html_inputfield([
            'name' => text,
            'id' => text,
            'class' => 'reset',
            'size' => 100,
            'autocomplete' => 'off',
            'autofocus' => true,
            'placeholder' => $this->gettext('examplereply'),
            'value' => $kas_data['mail_responder_text'],
        ]);

        $table->add('text', html::label(text, rcube::Q($this->gettext(text))));
        $table->add(null, $input_text->show());

        foreach (['save', 'delete'] as $value)
        {
            ${'button'.'_'.$value} = $rcmail->output->button([
                'id'      => $value,
                'command' => $value == 'save' ? 'plugin.allinkl_responder_set' : 'plugin.allinkl_responder_rst',
                'type'    => 'input',
                'class'   => 'button mainaction',
                'label'   => $value,
            ]);
        }

        $out = html::div(['class' => 'box'],
            html::div(['id' => 'prefs-title', 'class' => 'boxtitle'], $this->gettext('managereplies')) .
            html::div(['class' => 'boxcontent'],
                $table->show() .
                html::p(null, $this->gettext('responderinformation')) .
                html::p(null, $button_save . $button_delete) . 
                html::div(['id' => 'js-responder', 'style' => 'display: none'],
                    html::p(null, $kas_data['mail_responder'])
                )
            )
        );

        $rcmail->output->add_gui_object('form_reply', 'form_reply_id');

        return $rcmail->output->form_tag([
            'id'     => 'form_reply_id',
            'name'   => 'form_reply',
            'method' => 'post',
            'action' => './?_task=settings&_action=plugin.allinkl_responder_set',
        ], $out);
    }

    private function get_kas_data() 
    {
        if ($this->interact_with_kas('get_mailaccounts')) {
            // Search responders of mail account
            foreach ($this->kas_result as $mail_information) {
                if ($mail_information['mail_adresses'] == $_SESSION['username']) {
                    return $mail_information;
                }
            }
        }
    }

    function set_reply()
    {
        $this->register_handler('plugin.body', [$this, 'get_reply']);

        $rcmail = rcmail::get_instance();
        $rcmail->output->set_pagetitle($this->gettext('managereplies'));

        foreach (['from', 'until', 'text'] as $value)
        {
            $$value = trim(rcube_utils::get_input_value($value, rcube_utils::INPUT_POST));
        }
 
        if ($this->set_kas_responder($from, $until, $text))
        {
            $rcmail->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
        }
        // Important: wait a bit before returning, because otherwise
        // allinkl.com may respond with a flood_protection
        usleep(500000);
        $rcmail->overwrite_action('plugin.allinkl_responder_get');
        $rcmail->output->send('plugin');
    }
    
    function set_reply_rst() {} 

    private function set_kas_responder($from, $until, $text)
    {
        $parameters = [
            'mail_login' => $this->get_kas_data()['mail_login'],
            'responder' => empty($from) ? 'N' : strtotime($from) . '|' . strtotime($until),
            'responder_text' => $text,
        ];
        return $this->interact_with_kas('update_mailaccount', $parameters);
    }
        
    private function interact_with_kas($request_type, $parameters = [])
    {
        $this->kas_result = [];
        $rcmail = rcmail::get_instance();
        $WSDL_AUTH = 'https://kasserver.com/schnittstelle/soap/wsdl/KasAuth.wsdl';
        $WSDL_API = 'https://kasserver.com/schnittstelle/soap/wsdl/KasApi.wsdl';
        // Create SOAP-Session to KAS-Server
        try {
            $SoapLogon = new SoapClient($WSDL_AUTH);
            $CredentialToken = $SoapLogon->KasAuth([
                'KasUser' => $rcmail->config->get('allinkl_responder_user'),
                'KasAuthType' => 'sha1',
                'KasPassword' => sha1($rcmail->config->get('allinkl_responder_passwd')),
                'SessionLifeTime' => 30,
                'SessionUpdateLifeTime' => 'Y'
            ]);
        } catch (SoapFault $fault) {
            rcmail::raise_error([
                'code' => 600,
                'type' => 'php',
                'file' => __FILE__,
                'line' => __LINE__,
                'message' => 'Allinkl responder plugin: ' . $fault->faultstring
            ], true, false);
            $rcmail->output->command('display_message', $this->gettext('errnosoaplogon'), 'error');
            return false;
        }
        // Execute the request
        try {
            $SoapRequest = new SoapClient($WSDL_API);
            $req = $SoapRequest->KasApi(json_encode([
                'KasUser' => $rcmail->config->get('allinkl_responder_user'),
                'CredentialToken' => $CredentialToken,
                'KasRequestType' => $request_type,
                'KasRequestParams' => $parameters
            ]));
        } catch (SoapFault $fault) {
            rcmail::raise_error([
                'code' => 600,
                'type' => 'php',
                'file' => __FILE__,
                'line' => __LINE__,
                'message' => 'Allinkl responder plugin: ' . $fault->faultstring
            ], true, false);
            // Use specific error messages for certain errors.
            if (strpos($fault->faultstring, 'in_progress') === 0) {
                $rcmail->output->command('display_message', $this->gettext('errpreviousinprogress'), 'error');
            }
            elseif (strpos($fault->faultstring, 'nothing_to_do') === 0) {
                $rcmail->output->command('display_message', $this->gettext('errnothingtodo'), 'notice');
            }
            elseif (strpos($fault->faultstring, 'copy_adress_syntax_incorrect') === 0) {
                $rcmail->output->command('display_message', $this->gettext('errsyntaxincorrect'), 'error');
            }
            else {
                $rcmail->output->command('display_message', $this->gettext('errbadrequest'), 'error');
            }
            return false;
        }
        $this->kas_result = $req['Response']['ReturnInfo'];
        return true;
    }
}

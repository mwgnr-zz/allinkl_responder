/**
 * Password plugin script
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
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
 * @licend  The above is the entire license notice
 * for the JavaScript code in this file.
 */

window.rcmail && rcmail.addEventListener('init', function(evt) {
    // hide delete button if there is no autoresponder set 
    if (rcmail.env.action.match(/allinkl_responder/)) {
        var responder = $("#js-responder")[0].textContent;
        if (responder.match(/^N/)) {
            $("#delete").hide();
        }
    }
    // register command handler
    rcmail.register_command('plugin.allinkl_responder_set', function() {
        rcmail.gui_objects.form_reply.submit();
    }, true);
    rcmail.register_command('plugin.allinkl_responder_rst', function() {
        $(".reset").val('');
        rcmail.gui_objects.form_reply.submit();
    }, true);
});

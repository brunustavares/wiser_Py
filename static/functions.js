/**
 * wiser.Py
 * Python app for integrated data and indicators management
 * related to the tests carried out in WISEflow and Moodle.
 * (developed for UAb - Universidade Aberta)
 *
 * @package    wiser.Py
 * @category   app
 * @author     Bruno Tavares <brunustavares@gmail.com>
 * @link       https://www.linkedin.com/in/brunomastavares/
 * @copyright  Copyright (C) 2024-present Bruno Tavares
 * @license    GNU General Public License v3 or later
 *             https://www.gnu.org/licenses/gpl-3.0.html
 * @version    2026061809
 * @date       2024-02-20
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

// identificação da versão
window.onload = function () {
    if (window.location.port === "5099") {
        document.getElementById("version-label").innerText = "versão de TESTE";
    }
};

// transição suave entre páginas
document.addEventListener("DOMContentLoaded", () => {
    window.setTimeout(function() {
        document.body.className = '';
    }, 180);
} );

// redução da margem superior do menu principal, para acomodar opções de administração
function set_admin_layout() {
    document.getElementById("main-menu").style.marginTop  = "-10%";
}

// reposição do conteúdo na parte superior da página, para acomodar resultados
function set_loaded(divID) {
    let divElement = document.getElementById(divID);
    
    window.setTimeout(function() {
        divElement.setAttribute('id', "loaded");
    }, 500);
}

// configuração das tabelas de dados do WISEflow
$(document).ready(function() {
    $('#full_report_table').DataTable({
        language: {
            // personalizar botões de paginação: usar símbolos em vez de texto
            'paginate': {
                'previous': '<span class="fa fa-chevron-left"></span>',
                'next': '<span class="fa fa-chevron-right"></span>'
            },
            // personalizar número de elementos a serem exibidos
            "lengthMenu": 'Mostrar <select class="form-control input-sm">'+
            '<option value="10">10</option>'+
            '<option value="20">20</option>'+
            '<option value="30">30</option>'+
            '<option value="40">40</option>'+
            '<option value="50">50</option>'+
            '<option value="-1">Todos</option>'+
            '</select> resultados'
        }
    })

    $('#stats_table').DataTable({
        // ordenação de colunas
        "order": [0,'asc'],
        language: {
            // personalizar botões de paginação: usar símbolos em vez de texto
            'paginate': {
                'previous': '<span class="fa fa-chevron-left"></span>',
                'next': '<span class="fa fa-chevron-right"></span>'
            },
            // personalizar número de elementos a serem exibidos
            "lengthMenu": 'Mostrar <select class="form-control input-sm">'+
            '<option value="10">10</option>'+
            '<option value="20">20</option>'+
            '<option value="30">30</option>'+
            '<option value="40">40</option>'+
            '<option value="50">50</option>'+
            '<option value="-1">Todos</option>'+
            '</select> resultados'
        }
    })

    $('#manage_users_table').DataTable({
        // ordenação de colunas
        "columnDefs": [
            { "orderable": false, "targets": [1, 2, 3, 4, 5, 6, 7, 11, 12] }
        ],
        language: {
            // personalizar botões de paginação: usar símbolos em vez de texto
            'paginate': {
                'previous': '<span class="fa fa-chevron-left"></span>',
                'next': '<span class="fa fa-chevron-right"></span>'
            },
            // personalizar número de elementos a serem exibidos
            "lengthMenu": 'Mostrar <select class="form-control input-sm">'+
            '<option value="10">10</option>'+
            '<option value="20">20</option>'+
            '<option value="30">30</option>'+
            '<option value="40">40</option>'+
            '<option value="50">50</option>'+
            '<option value="-1">Todos</option>'+
            '</select> resultados'
        }
    })

    $('#sentinelf_main_table').DataTable({
        // ordenação de colunas
        ordering: false,
        searching: false,
        "order": [0,'asc'],
        language: {
            // personalizar botões de paginação: usar símbolos em vez de texto
            'paginate': {
                'previous': '<span class="fa fa-chevron-left"></span>',
                'next': '<span class="fa fa-chevron-right"></span>'
            },
            // personalizar número de elementos a serem exibidos
            "lengthMenu": 'Mostrar <select class="form-control input-sm">'+
            '<option value="10">10</option>'+
            '<option value="20">20</option>'+
            '<option value="30">30</option>'+
            '<option value="40">40</option>'+
            '<option value="50">50</option>'+
            '<option value="-1">Todos</option>'+
            '</select> resultados'
        }
    })

    $('#activity_log_table').DataTable({
        // ordenação de colunas
        "order": [5,'desc'],
        "columnDefs": [
            { "orderable": false, "targets": 2 }
        ],
        language: {
            // personalizar botões de paginação: usar símbolos em vez de texto
            'paginate': {
                'previous': '<span class="fa fa-chevron-left"></span>',
                'next': '<span class="fa fa-chevron-right"></span>'
            },
            // personalizar número de elementos a serem exibidos
            "lengthMenu": 'Mostrar <select class="form-control input-sm">'+
            '<option value="10">10</option>'+
            '<option value="20">20</option>'+
            '<option value="30">30</option>'+
            '<option value="40">40</option>'+
            '<option value="50">50</option>'+
            '<option value="-1">Todos</option>'+
            '</select> resultados'
        }
    })  

    $('#mail_log_table').DataTable({
        // ordenação de colunas
        "order": [3,'desc'],
        "columnDefs": [
            { "orderable": false, "targets": [0, 1, 2] }
        ],
        language: {
            // personalizar botões de paginação: usar símbolos em vez de texto
            'paginate': {
                'previous': '<span class="fa fa-chevron-left"></span>',
                'next': '<span class="fa fa-chevron-right"></span>'
            },
            // personalizar número de elementos a serem exibidos
            "lengthMenu": 'Mostrar <select class="form-control input-sm">'+
            '<option value="10">10</option>'+
            '<option value="20">20</option>'+
            '<option value="30">30</option>'+
            '<option value="40">40</option>'+
            '<option value="50">50</option>'+
            '<option value="-1">Todos</option>'+
            '</select> resultados'
        }
    })  
});

// acesso directo aos dados do estudante
function get_user_info(type) {
    let std_num = document.getElementById(type).value;
    let params = {};

    if (type == "usr_graph") {
        params["student"] = std_num;
        params["report"] = "report_all";

        post("user_report", params);

    } else if (type == "usr_sentinelf") {
        params["student"] = std_num;
        params["events"] = "by_student";

        post("sentinelf_reported", params);

    } else if (type == "usr_bio") {
        params["student"] = std_num;

        post("user_bio", params);
    }
}

// alteração do estado da prova
function set_std_flw_status(info, notify=false) {
    let params = {};

    params["std_num"] = info.split(";")[0];
    params["flowid"] = info.split(";")[1];

    let type = info.split(";")[2];

    if (type == "flw_warn") {
        params["set_status"] = "0";

    } else if (type == "flw_null_bio") {
        params["set_status"] = "2";

    } else if (type == "flw_null_irreg") {
        params["set_status"] = "3";

    } else if (type == "flw_null_copy") {
        params["set_status"] = "5";

    } else if (type == "flw_null_mdl") {
        params["set_status"] = "6";

    } else if (type == "flw_revert") {
        params["set_status"] = "9";

    } else if (type == "flw_inloco") {
        params["set_status"] = "4";

    } else {
        params["set_status"] = "null";
    }

    if (notify) {
        params["notify"] = "True";

    } else {
        params["notify"] = "False";
    }

    post("wf_full_report", params);
}

// carregamento dos dados do LLM seleccionado
function load_llm_info(select) {
    const selected = select.options[select.selectedIndex];
    const llm = JSON.parse(selected.dataset.llm);

    let llm_id = document.getElementById("llm_id");
    let llm_provider = document.getElementById("llm_provider");
    let llm_user = document.getElementById("llm_user");
    let llm_endpoint = document.getElementById("llm_endpoint");
    let llm_agent_id = document.getElementById("llm_agent_id");
    let llm_api_key = document.getElementById("llm_api_key");
    let llm_channel_id = document.getElementById("llm_channel_id");
    let llm_model_name = document.getElementById("llm_model_name");
    let llm_primary = document.getElementById("llm_primary");
    let llm_secondary = document.getElementById("llm_secondary");

    let del_llm = document.getElementById("del_llm");

    llm_id.value = llm[0];
    llm_provider.value = llm[1];
    llm_user.value = llm[2];
    llm_endpoint.value = llm[3];
    llm_agent_id.value = llm[4];
    llm_api_key.value = llm[5];
    llm_channel_id.value = llm[6];
    llm_model_name.value = llm[7];

    llm_primary.checked = false;
    llm_secondary.checked = false;

    if (llm[8] == 1) {
        llm_primary.checked = true;
    }

    if (llm[8] == 2) {
        llm_secondary.checked = true;
    }

    del_llm.value = llm[0];
}

// registo de novo LLM
function new_LLM() {
    const select = document.getElementById("llms");

    const option = document.createElement("option");
    option.value = "novo";
    option.text = "novo LLM";
    option.selected = true;

    select.appendChild(option);

    document.getElementById("llm_id").value = null;
    document.getElementById("llm_provider").value = null;
    document.getElementById("llm_user").value = null;
    document.getElementById("llm_endpoint").value = null;
    document.getElementById("llm_agent_id").value = null;
    document.getElementById("llm_api_key").value = null;
    document.getElementById("llm_channel_id").value = null;
    document.getElementById("llm_model_name").value = null;
    document.getElementById("llm_primary").value = null;
    document.getElementById("llm_secondary").value = null;
    document.getElementById("llm_primary").checked = false;
    document.getElementById("llm_secondary").checked = false;
}

// controlo de exclusividade entre checkboxes
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".exclusive-check").forEach(cb => {
        cb.addEventListener("change", function () {
            if (!this.checked) return;

            document.querySelectorAll(".exclusive-check").forEach(other => {
                if (other !== this) {
                    other.checked = false;
                }
            });
        });
    });
});

// associação do ID do LLM p/ eliminação
function send_ID(llm) {
    document.getElementById("go").value = llm;
}

// alteração da visibilidade da password
function togglePassword(pwd) {
    const wrapper = pwd.closest('.password-field');
    const input = wrapper.querySelector('input');
    const show = pwd.querySelector('.fa-icon');
    const hide = pwd.querySelector('.fa-icon-hide');

    if (input.type === "password") {
        input.type = "text";
        show.style.display = "none";
        hide.style.display = "block";
    } else {
        input.type = "password";
        show.style.display = "block";
        hide.style.display = "none";
    }
}

// download do ficheiro gerado e redirecionamento para a pesquisa
function downloadFile(file_url, file_name, page) {
    const link = document.createElement('a');

    link.href = file_url;
    link.download = file_name;

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    setTimeout(function() {
        go_to(page);
    }, 1000);
}

// abertura do PDF gerado numa tab independente
function openPDF(file_url) {
    const newTab = window.open(file_url, '_blank');

    if (!newTab) {
        alert('Para aceder ao ficheiro, os pop-ups devem estar activados neste site.');
    }

    newTab.focus();
}

// link directo
function go_to(url) {
    let link = document.createElement('a');

    link.href = url;
    link.click();
}

// controlo de disponibilidade do botão de upload da imagem de referência
function enable_upload() {
    document.getElementById("upload").disabled = false;
}

// controlo de disponibilidade dos perfis dos utilizadores
function update_users(usrid) {
    let admin = document.getElementById("admin".concat(usrid));
    let flowman = document.getElementById("flowman".concat(usrid));
    let bioman = document.getElementById("bioman".concat(usrid));
    let statsman = document.getElementById("statsman".concat(usrid));
    let toolsusr = document.getElementById("toolsusr".concat(usrid));
    let sosusr = document.getElementById("sosusr".concat(usrid));
    let suspended = document.getElementById("suspended".concat(usrid));
    let params = {};

    params["usrid"] = usrid;

    if (admin.checked) { params["admin"] = "1"; } else { params["admin"] = "0"; }

    if (flowman.checked) { params["flowman"] = "1"; } else { params["flowman"] = "0"; }

    if (bioman.checked || flowman.checked) { params["bioman"] = "1"; } else { params["bioman"] = "0"; }

    if (statsman.checked) { params["statsman"] = "1"; } else { params["statsman"] = "0"; }

    if (toolsusr.checked) { params["toolsusr"] = "1"; } else { params["toolsusr"] = "0"; }

    if (sosusr.checked && toolsusr.checked) { params["sosusr"] = "1"; } else { params["sosusr"] = "0"; }

    if (suspended.checked) { params["suspended"] = "1"; } else { params["suspended"] = "0"; }

    post("manage_users", params);
}

// enviar dados do estudante e da prova para os menus de opções
function attach_std_flw_id(std_num, std_name=null, flw_id=null) {
    let std_opt_title = document.getElementById("modal-title-std_opt");
    let flw_opt_title = document.getElementById("modal-title-flw_opt");

    let graph_button = document.getElementById("usr_graph");
    let sentinelf_button = document.getElementById("usr_sentinelf");
    let bio_button = document.getElementById("usr_bio");

    let yes_button = document.getElementById("yes");
    let no_button = document.getElementById("no");

    if (std_name) {
        std_opt_title.innerHTML = std_num.concat(" | ").concat(std_name);
        flw_opt_title.innerHTML = std_num.concat(" | ").concat(std_name);
    } else {
        std_opt_title.innerHTML = std_num;
    }

    graph_button.value = std_num;
    if (sentinelf_button) { sentinelf_button.value = std_num; }
    bio_button.value = std_num;

    yes_button.value = std_num.concat(";").concat(flw_id);
    no_button.value = std_num.concat(";").concat(flw_id);
}

function attach_flw_status(flw_status) {
    let yes_button = document.getElementById("yes");
    let no_button = document.getElementById("no");
    let flowid = yes_button.value.split(";")[1];
    let std_num = yes_button.value.split(";")[0];
    let status = "";

    yes_button.value = yes_button.value.concat(";").concat(flw_status);
    no_button.value = no_button.value.concat(";").concat(flw_status);

    if (flw_status == "flw_warn") {
        status = " | advertência";

    } else if (flw_status == "flw_null_bio") {
        status = " | anulação p/ biometria";

    } else if (flw_status == "flw_null_irreg") {
        status = " | anulação p/ realiz.irreg.";

    } else if (flw_status == "flw_null_copy") {
        status = " | anulação p/ cópia indevida";

    } else if (flw_status == "flw_null_mdl") {
        status = " | anulação p/ consulta PlataformAbERTA";

    } else if (flw_status == "flw_revert") {
        status = " | reposição";

    } else if (flw_status == "flw_inloco") {
        status = " | presencial";

    } else {
        status = " | regular";
    }

    let prefix = "fluxo ";
    let infix = "<br>notificar estudante ";
    let suffix = "?";

    document.getElementById("student").innerHTML = prefix.concat(flowid).concat(status).concat(infix).concat(std_num).concat(suffix);
}

// confirmação de operações de eliminação de eventos
function send_event_info(evt) {
    let button = document.getElementById("go");

    button.value = evt;

    let preffix = "Apagar ";

    let infix = preffix.concat("evento '");
    let suffix = "'?";

    document.getElementById("event").innerHTML = infix.concat(evt).concat(suffix);
}

// confirmação de operações de reinicialização de password e eliminação de utilizadores
function send_values(usr, op) {
    let button = document.getElementById("go");

    button.name = op;
    button.value = usr;

    let preffix = "";

    if (op == "rst") {
        preffix = "Reiniciar password do ";

    } else if (op == "del") {
        preffix = "Apagar ";
    }

    let infix = preffix.concat("utilizador '");
    let suffix = "'?";

    document.getElementById("user").innerHTML = infix.concat(usr).concat(suffix);
}

// envio de post requests para as forms
function post(form, params=null) {
    let action = "/" . concat(form)
    let hiddenForm = document.getElementById(form);

    if (hiddenForm == null) {
        hiddenForm = document.createElement("form");

        hiddenForm.setAttribute('method', 'post');
        hiddenForm.setAttribute('action', action);

        hiddenForm.setAttribute('target', "_blank");

    } else {
        hiddenForm.setAttribute('onsubmit', showModal());
    }

    for (const key in params) {
        let hiddenField = document.createElement('input');

        hiddenField.setAttribute('type', 'hidden');
        hiddenField.setAttribute('name', key);
        hiddenField.setAttribute('value', params[key]);

        hiddenForm.appendChild(hiddenField);
    }

    document.body.appendChild(hiddenForm);

    hiddenForm.submit();
}

// função para submeter a prompt na Queryn com a tecla Enter
function submitOnEnter(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        
        const submitButton = document.getElementById('send_prompt');
        
        if (submitButton) {
            submitButton.click();
        }
    }
}

// visibilidade da div de configuração
function ShowHideDiv(divID01, divID02=null, set_loaded=false) {
    let div01 = document.getElementById(divID01);
    let div02 = document.getElementById(divID02);
    let unloaded = document.getElementById("unloaded");
    let loaded = document.getElementById("loaded");

    if (div02) { div02.classList.toggle("show"); }
    div01.classList.toggle("show");
    
    if (set_loaded) {
        if (unloaded) {
            unloaded.setAttribute('id', "loaded");
        } else {
            loaded.setAttribute('id', "unloaded");
        }
    }
}

// visibilidade da janela de modelo de mensagem
function showMailDiv(divID, hideNew) {
    let mailDiv = document.getElementById(divID);

    mailDiv.style.display = "block";
    setTimeout(function() {
        mailDiv.style.opacity = 1;

        if (hideNew) {
            document.getElementById("add_template").style.visibility = "hidden";
        }
    }, 250);
}

// verificação de condições para activação de botões
function checkUpdtReqs(sect, id, comp=null) {
    if (sect == "mail_div") {
        const optID = "notif_type_"+ id;
        const selection = document.getElementById(optID).value;
        const bodyID = "msg_"+ id;
        // const text = document.getElementById(bodyID).value;
        const text = quill[id].root.innerHTML;
        const btnID = "updt_template_"+ id;
        const updtBtn = document.getElementById(btnID);
        const iconID = btnID + "_icon";
        const updtBtnIcon = document.getElementById(iconID);

        if (selection != "tipo de notificação" && text.trim() != "") {
            if (comp) {
                if (selection != comp["prev_sel"]
                    || text.trim() != comp["prev_text"].trim()) {
                    updtBtnIcon.className.baseVal = "fa-icon";
                    updtBtn.disabled = false;

                } else {
                    updtBtnIcon.className.baseVal = "fa-icon-disabled";
                    updtBtn.disabled = true;
                }
    
            } else {
                updtBtnIcon.className.baseVal = "fa-icon";
                updtBtn.disabled = false;
            }
        } else {
            updtBtnIcon.className.baseVal = "fa-icon-disabled";
            updtBtn.disabled = true;
        }

    } else if (sect == "mail_server") {
        const server = document.getElementById("server").value;
        const port = document.getElementById("port").value;
        const username = document.getElementById("username").value;
        const password = document.getElementById("password").value;
        const mailbox = document.getElementById("mailbox").value;
        const updtBtn = document.getElementById("save_server");

        if (server.trim() != "" && mailbox.trim() != "") {
            if (comp) {
                if (server.trim() != comp["prev_server"].trim()
                    || port.trim() != comp["prev_port"].trim()
                    || username.trim() != comp["prev_username"].trim()
                    || password.trim() != comp["prev_password"].trim()
                    || mailbox.trim() != comp["prev_mailbox"].trim()) {
                    updtBtn.disabled = false;

                } else {
                    updtBtn.disabled = true;
                }
            } else {
                updtBtn.disabled = false;
            }
        } else {
            updtBtn.disabled = true;
        }
    }
}

// gestão da disponibilidade dos parâmetros p/ os endpoints AIDA
document.addEventListener("DOMContentLoaded", function() {
    document.getElementById("endpoint").addEventListener("change", function() {
        if (this.value === "estudantes_NEEs"
            || this.value === "endpoint") {
            show_param("div_lect_year", false);
        } else {
            show_param("div_lect_year", true);
        }
        if (this.value === "estudantes_avaliacao"
            || this.value === "estudantes_folios"
            || this.value === "estudantes_MAO"
            || this.value === "estudantes_NEEs"
            || this.value === "estudantes_UC_acesso"
            || this.value === "estudantes_UC_actividade"
            || this.value === "estudantes_UC_escolha"
            || this.value === "estudantes_WF_escolha"
            || this.value === "folio_historico_data") {
            show_param("div_students", true);
        } else {
            show_param("div_students", false);
        }
        if (this.value === "estudantes_avaliacao"
            || this.value === "estudantes_folios"
            || this.value === "estudantes_UC_acesso"
            || this.value === "estudantes_UC_actividade"
            || this.value === "estudantes_UC_escolha"
            || this.value === "folio_historico_data"
            || this.value === "iduc"
            || this.value === "ucs_flows_tipo") {
            show_param("div_courses", true);
        } else {
            show_param("div_courses", false);
        }
        if (this.value === "estudantes_folios"
            || this.value === "folio_historico_data") {
            set_Mandatory_icon("icon_courses", true);
            set_Mandatory_text("title_courses", true);
            if (this.value === "folio_historico_data") {
                set_Mandatory_icon("icon_students", true);
                set_Mandatory_text("title_students", true);
            } else {
                set_Mandatory_icon("icon_students", false);
                set_Mandatory_text("title_students", false);
            }
        } else {
            set_Mandatory_icon("icon_courses", false);
            set_Mandatory_text("title_courses", false);
        }
        if (this.value === "estudantes_WF_escolha") {
            show_param("div_WF_option", true);
        } else {
            show_param("div_WF_option", false);
        }
        if (this.value === "estudantes_NEEs") {
            show_param("div_NEE_status", true);
        } else {
            show_param("div_NEE_status", false);
        }
        if (this.value === "iduc") {
            show_param("div_IdUC", true);
        } else {
            show_param("div_IdUC", false);
        }
    });
});

// gestão da disponibilidade dos parâmetros p/ gestão dos fluxos
document.addEventListener("DOMContentLoaded", function() {
    document.getElementById("function").addEventListener("change", function() {
        if (this.value === "extend_flow") {
            show_param("div_extra_time", true);
        } else {
            show_param("div_extra_time", false);
        }
    });
});

// gestão da disponibilidade do flowid no histórico
document.addEventListener("DOMContentLoaded", function() {
    document.getElementById("report_all").addEventListener("change", function() {
        if (this.checked) {
            show_param("div_courses", false);
        }
    });
    document.getElementById("report_single").addEventListener("change", function() {
        if (this.checked) {
            show_param("div_courses", true);
        }
    });
});

// gestão da disponibilidade do critério p/ acesso aos reports do Sentinel_F
document.addEventListener("DOMContentLoaded", function() {
    document.getElementById("by_date").addEventListener("change", function() {
        if (this.checked) {
            show_param("student", false);
            show_param("date", true);
        }
    });
    document.getElementById("by_student").addEventListener("change", function() {
        if (this.checked) {
            show_param("date", false);
            show_param("student", true);
        }
    });
});

// gestão da disponibilidade dos parâmetros p/ os LLMs
document.addEventListener("DOMContentLoaded", function () {
    const llmSelect = document.getElementById("llms");

    llmSelect.addEventListener("change", function () {
        load_llm_info(this);
    });

    load_llm_info(llmSelect);
});

// ocultação/exibição de elementos
function show_param(element, show) {
    let param = document.getElementById(element);

    if (show) {
        param.style.display = "block";
    } else {
        param.style.display = "none";
    }
}

// alteração da cor dos ícones de acordo com a obrigatoriedade dos campos
function set_Mandatory_icon(element, mandatory) {
    let param = document.getElementById(element);

    if (mandatory) {
        param.classList.remove('fa-icon_optional');
        param.classList.add('fa-icon_mandatory');
    } else {
        param.classList.remove('fa-icon_mandatory');
        param.classList.add('fa-icon_optional');
    }
}

// alteração dos textos de acordo com a obrigatoriedade dos campos
function set_Mandatory_text(element, mandatory) {
    let param = document.getElementById(element);

    if (mandatory) {
        param.innerHTML = "obrigatório";
    } else {
        param.innerHTML = "opcional";
    }
}

// activação da janela modal em processamento
function showModal() {
    document.getElementById("loading").style.display = "block";
    document.getElementById("loading").style.opacity = "1";
}

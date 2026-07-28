/* Validacion LLM al guardar - syllabus catedratico (requiere strActionLLM, ICONO_IA_URL, summernote*Init) */

/** Devuelve texto plano de HTML (Summernote); vacio si solo hay etiquetas vacias */
function textoPlanoDesdeHtml(html) {
    if (!html) return '';
    var div = document.createElement('div');
    div.innerHTML = html;
    return (div.textContent || div.innerText || '').replace(/\s+/g, ' ').trim();
}

function escapeHtmlTexto(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

var LLM_CAMPOS_SUMMERNOTE = {
    normas: {
        cfgKey: 'normas',
        editedId: 'hidEditedNormas',
        ultimoValidadoId: 'hidUltimoValidadoNormas',
        estadoId: 'hidEstadoLLMNormas',
        aceptadoId: 'hidAceptadoManualNormas',
        divResultadoId: 'divResultadoLLMNormas',
        wrapEditorId: 'wrapEditorNormas',
        textareaId: 'txtNormas',
        initFlag: 'summernoteNormasInit',
        nombreCampo: 'Normas',
        placeholder: 'Escriba las normas y reglas operativas del curso...'
    },
    usoIA: {
        cfgKey: 'usoIA',
        editedId: 'hidEditedUsoIA',
        ultimoValidadoId: 'hidUltimoValidadoUsoIA',
        estadoId: 'hidEstadoLLMUsoIA',
        aceptadoId: 'hidAceptadoManualUsoIA',
        divResultadoId: 'divResultadoLLMUsoIA',
        wrapEditorId: 'wrapEditorUsoIA',
        textareaId: 'txtUsoIA',
        initFlag: 'summernoteUsoIAInit',
        nombreCampo: 'UsoIA',
        placeholder: 'Describa el uso de IA permitido o prohibido, citacion y limites en su curso...'
    },
    pensamientoCritico: {
        cfgKey: 'pensamientoCritico',
        editedId: 'hidEditedPensamientoCritico',
        ultimoValidadoId: 'hidUltimoValidadoPensamientoCritico',
        estadoId: 'hidEstadoLLMPensamientoCritico',
        aceptadoId: 'hidAceptadoManualPensamientoCritico',
        divResultadoId: 'divResultadoLLMPensamientoCritico',
        wrapEditorId: 'wrapEditorPensamientoCritico',
        textareaId: 'txtPensamientoCritico',
        initFlag: 'summernotePensamientoCriticoInit',
        nombreCampo: 'PensamientoCritico',
        placeholder: 'Describa las estrategias y evidencias para el desarrollo del pensamiento critico en su curso...'
    }
};

function llmAjaxSincrono(data) {
    var resultado = null;
    $.ajax({
        url: strActionLLM,
        type: 'POST',
        data: data,
        async: false,
        dataType: 'json',
        success: function(r) { resultado = r; },
        error: function() { resultado = { error: 'Error al conectar con el servidor' }; }
    });
    return resultado;
}

function llmEncabezadoIA() {
    return '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">' +
        '<img src="' + ICONO_IA_URL + '" alt="IA" style="height:25px;">' +
        '<span style="font-size:12px;color:#666;">An&aacute;lisis generado con IA</span></div>';
}

function llmEncabezadoIANormas() {
    return '<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">' +
        '<img src="' + ICONO_IA_URL + '" alt="IA" style="height:28px;">' +
        '<span style="font-size:14px;color:#555;font-weight:500;">An&aacute;lisis generado con IA</span></div>';
}

function llmEncabezadoIABiblio() {
    return '<div style="display:flex;align-items:center;gap:6px;margin-bottom:10px;">' +
        '<img src="' + ICONO_IA_URL + '" alt="IA" style="height:25px;">' +
        '<span style="font-size:12px;color:#666;">An&aacute;lisis generado con IA</span></div>';
}

function scrollAlPrimerFeedbackLLM() {
    var destino = null;

    ['normas', 'usoIA', 'pensamientoCritico'].some(function(key) {
        var cfg = LLM_CAMPOS_SUMMERNOTE[key];
        if ($('#' + cfg.editedId).val() !== 'Y') return false;
        var estado = $('#' + cfg.estadoId).val();
        var div = document.getElementById(cfg.divResultadoId);
        if (estado && estado !== 'correcto' && div && div.offsetParent !== null) {
            destino = div;
            return true;
        }
        return false;
    });

    if (!destino) {
        $('[id^="divResultadoLLMRubroEval_"]').each(function() {
            if (this.offsetParent === null) return true;
            var n = this.id.replace('divResultadoLLMRubroEval_', '');
            var estado = $('#hidEstadoLLMRubroEval_' + n).val() || $(this).attr('data-estado') || '';
            if (estado && estado !== 'correcto') {
                destino = this;
                return false;
            }
        });
    }

    if (!destino) {
        $('[id^="divResultadoLLMBiblio_"]').each(function() {
            if (this.offsetParent === null) return true;
            var n = this.id.replace('divResultadoLLMBiblio_', '');
            var estado = $('#hidEstadoLLMBiblio_' + n).val() || $(this).attr('data-estado') || '';
            if (estado && estado !== 'correcto') {
                destino = this;
                return false;
            }
        });
    }

    if (!destino) {
        $('[id^="divResultadoLLMExp_"]').each(function() {
            if (this.offsetParent === null) return true;
            var n = this.id.replace('divResultadoLLMExp_', '');
            var estado = $('#hidEstadoLLMExp_' + n).val() || $(this).attr('data-estado') || '';
            if (estado && estado !== 'correcto') {
                destino = this;
                return false;
            }
        });
    }

    if (destino) {
        setTimeout(function() {
            destino.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 350);
    }
}

function llmBotonesAceptarAplicar(onAplicar, onAceptar, labelAplicar, labelAceptar, colorAplicar) {
    labelAplicar = labelAplicar || 'Aplicar sugerencia';
    labelAceptar = labelAceptar || 'Aceptar actual como correcto';
    colorAplicar = colorAplicar || '#2196F3';
    var html = '<div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">';
    if (onAplicar) {
        html += '<button type="button" onclick="' + onAplicar + ';return false;" ' +
            'style="padding:5px 15px;background:' + colorAplicar + ';color:#fff;border:none;border-radius:4px;' +
            'cursor:pointer;font-weight:bold;font-size:12px;">' + labelAplicar + '</button>';
    }
    if (onAceptar) {
        html += '<button type="button" onclick="' + onAceptar + ';return false;" ' +
            'style="padding:5px 15px;background:#4CAF50;color:#fff;border:none;border-radius:4px;' +
            'cursor:pointer;font-weight:bold;font-size:12px;">' + labelAceptar + '</button>';
    }
    html += '</div>';
    return html;
}

function mostrarResultadoCampoSummernote(divId, wrapId, aplicarFn, aceptarFn, resultado) {
    var div = $('#' + divId);
    var html = llmEncabezadoIANormas();

    if (resultado.estado === 'correcto') {
        html += '<div style="color:#155724;background:#d4edda;padding:10px;border-radius:4px;border-left:4px solid #28a745;">' +
            '<strong>Texto correcto</strong></div>';
        $('#' + wrapId + ' .note-editor').css('border', '2px solid #28a745');
    } else {
        var esIncorrecto = resultado.estado === 'incorrecto';
        var color = esIncorrecto ? '#721c24' : '#856404';
        var bg = esIncorrecto ? '#f8d7da' : '#fff3cd';
        var border = esIncorrecto ? '#dc3545' : '#ffc107';
        var titulo = esIncorrecto ? 'Texto incorrecto' : 'Puede mejorarse';
        html += '<div style="color:' + color + ';background:' + bg + ';padding:10px;border-radius:4px;border-left:4px solid ' + border + ';">';
        html += '<strong>' + titulo + '</strong>';
        if (resultado.explicacion) html += '<br>' + resultado.explicacion;
        var labelAplicar = esIncorrecto ? 'Aplicar corrección' : 'Aplicar sugerencia';
        var colorAplicar = esIncorrecto ? '#4CAF50' : '#2196F3';
        var onAceptar = esIncorrecto ? '' : aceptarFn;
        if (resultado.html_corregido && resultado.html_corregido.trim() !== '') {
            html += '<br><br><strong>Vista previa del texto corregido:</strong>';
            html += '<div style="margin:10px 0;padding:10px;background:#f8f9fa;border:1px solid #dee2e6;border-radius:4px;">';
            html += resultado.html_corregido + '</div>';
            div.attr('data-html-corregido', resultado.html_corregido);
            html += llmBotonesAceptarAplicar(aplicarFn, onAceptar, labelAplicar, 'Aceptar texto actual como correcto', colorAplicar);
        } else if (!esIncorrecto) {
            html += llmBotonesAceptarAplicar('', aceptarFn, '', 'Aceptar texto actual como correcto');
        }
        html += '</div>';
        $('#' + wrapId + ' .note-editor').css('border', '2px solid ' + border);
    }
    div.html(html).show();
}

function validarCampoSummernoteCatedratico(opts) {
    if ($('#' + opts.editedId).val() !== 'Y') {
        return true;
    }
    if ($('#' + opts.aceptadoId).val() === 'Y') {
        return true;
    }

    var textoHTML = '';
    if (opts.summernoteInit()) {
        textoHTML = $.trim($('#' + opts.textareaId).summernote('code') || '');
    } else {
        textoHTML = $.trim($('#' + opts.textareaId).val() || '');
    }

    if (textoPlanoDesdeHtml(textoHTML) === '') {
        return true;
    }

    var ultimoValidado = $('#' + opts.ultimoValidadoId).val();
    if (ultimoValidado === textoHTML) {
        return $('#' + opts.estadoId).val() !== 'incorrecto';
    }

    $('#' + opts.divResultadoId).html(
        '<div style="padding:10px;background:#e7f3ff;border-radius:4px;"><strong>Validando texto...</strong></div>'
    ).show();

    var resultado = llmAjaxSincrono({
        validarDescripcion: true,
        textoHTML: textoHTML,
        nombreCampo: opts.nombreCampo
    });

    if (resultado && !resultado.error) {
        $('#' + opts.ultimoValidadoId).val(textoHTML);
        $('#' + opts.estadoId).val(resultado.estado);
        opts.mostrarResultado(resultado);
        return resultado.estado !== 'incorrecto';
    }

    $('#' + opts.divResultadoId).html(
        '<div style="padding:10px;background:#f8d7da;color:#721c24;border-radius:4px;">' +
        '<strong>Error:</strong> ' + (resultado && resultado.error ? resultado.error : 'Desconocido') +
        '</div>'
    ).show();
    return false;
}

function mostrarResultadoNormas(resultado) {
    mostrarResultadoCampoSummernote(
        'divResultadoLLMNormas',
        'wrapEditorNormas',
        'aplicarCorreccionNormas()',
        'aceptarComoCorrectoNormas()',
        resultado
    );
}

function aplicarCorreccionNormas() {
    var htmlCorregido = $('#divResultadoLLMNormas').attr('data-html-corregido') || '';
    if (summernoteNormasInit) {
        $('#txtNormas').summernote('code', htmlCorregido);
    } else {
        $('#txtNormas').val(htmlCorregido);
    }
    $('#hidEditedNormas').val('Y');
    $('#hidUltimoValidadoNormas').val('');
    $('#hidEstadoLLMNormas').val('');
    $('#hidAceptadoManualNormas').val('');
    $('#divResultadoLLMNormas').html(
        '<div style="padding:10px;background:#d4edda;color:#155724;border-radius:4px;">' +
        '<strong>Sugerencia/Corrección aplicada exitosamente</strong></div>').show();
    $('#wrapEditorNormas .note-editor').css('border', '');
}

function aceptarComoCorrectoNormas() {
    $('#hidEstadoLLMNormas').val('correcto');
    $('#hidAceptadoManualNormas').val('Y');
    var textoHTML = summernoteNormasInit
        ? $.trim($('#txtNormas').summernote('code') || '')
        : $.trim($('#txtNormas').val() || '');
    $('#hidUltimoValidadoNormas').val(textoHTML);
    $('#divResultadoLLMNormas').hide();
    $('#wrapEditorNormas .note-editor').css('border', '2px solid #28a745');
}

function validarNormasConLLM() {
    return validarCampoSummernoteCatedratico({
        editedId: 'hidEditedNormas',
        aceptadoId: 'hidAceptadoManualNormas',
        textareaId: 'txtNormas',
        ultimoValidadoId: 'hidUltimoValidadoNormas',
        estadoId: 'hidEstadoLLMNormas',
        divResultadoId: 'divResultadoLLMNormas',
        nombreCampo: 'Normas',
        summernoteInit: function() { return !!summernoteNormasInit; },
        mostrarResultado: mostrarResultadoNormas
    });
}

function mostrarResultadoUsoIA(resultado) {
    mostrarResultadoCampoSummernote(
        'divResultadoLLMUsoIA',
        'wrapEditorUsoIA',
        'aplicarCorreccionUsoIA()',
        'aceptarComoCorrectoUsoIA()',
        resultado
    );
}

function aplicarCorreccionUsoIA() {
    var htmlCorregido = $('#divResultadoLLMUsoIA').attr('data-html-corregido') || '';
    if (summernoteUsoIAInit) {
        $('#txtUsoIA').summernote('code', htmlCorregido);
    } else {
        $('#txtUsoIA').val(htmlCorregido);
    }
    $('#hidEditedUsoIA').val('Y');
    $('#hidUltimoValidadoUsoIA').val('');
    $('#hidEstadoLLMUsoIA').val('');
    $('#hidAceptadoManualUsoIA').val('');
    $('#divResultadoLLMUsoIA').html(
        '<div style="padding:10px;background:#d4edda;color:#155724;border-radius:4px;">' +
        '<strong>Sugerencia/Corrección aplicada exitosamente</strong></div>').show();
    $('#wrapEditorUsoIA .note-editor').css('border', '');
}

function aceptarComoCorrectoUsoIA() {
    $('#hidEstadoLLMUsoIA').val('correcto');
    $('#hidAceptadoManualUsoIA').val('Y');
    var textoHTML = summernoteUsoIAInit
        ? $.trim($('#txtUsoIA').summernote('code') || '')
        : $.trim($('#txtUsoIA').val() || '');
    $('#hidUltimoValidadoUsoIA').val(textoHTML);
    $('#divResultadoLLMUsoIA').hide();
    $('#wrapEditorUsoIA .note-editor').css('border', '2px solid #28a745');
}

function validarUsoIAConLLM() {
    return validarCampoSummernoteCatedratico({
        editedId: 'hidEditedUsoIA',
        aceptadoId: 'hidAceptadoManualUsoIA',
        textareaId: 'txtUsoIA',
        ultimoValidadoId: 'hidUltimoValidadoUsoIA',
        estadoId: 'hidEstadoLLMUsoIA',
        divResultadoId: 'divResultadoLLMUsoIA',
        nombreCampo: 'UsoIA',
        summernoteInit: function() { return !!summernoteUsoIAInit; },
        mostrarResultado: mostrarResultadoUsoIA
    });
}

function mostrarResultadoPensamientoCritico(resultado) {
    mostrarResultadoCampoSummernote(
        'divResultadoLLMPensamientoCritico',
        'wrapEditorPensamientoCritico',
        'aplicarCorreccionPensamientoCritico()',
        'aceptarComoCorrectoPensamientoCritico()',
        resultado
    );
}

function aplicarCorreccionPensamientoCritico() {
    var htmlCorregido = $('#divResultadoLLMPensamientoCritico').attr('data-html-corregido') || '';
    if (summernotePensamientoCriticoInit) {
        $('#txtPensamientoCritico').summernote('code', htmlCorregido);
    } else {
        $('#txtPensamientoCritico').val(htmlCorregido);
    }
    $('#hidEditedPensamientoCritico').val('Y');
    $('#hidUltimoValidadoPensamientoCritico').val('');
    $('#hidEstadoLLMPensamientoCritico').val('');
    $('#hidAceptadoManualPensamientoCritico').val('');
    $('#divResultadoLLMPensamientoCritico').html(
        '<div style="padding:10px;background:#d4edda;color:#155724;border-radius:4px;">' +
        '<strong>Sugerencia/Corrección aplicada exitosamente</strong></div>').show();
    $('#wrapEditorPensamientoCritico .note-editor').css('border', '');
}

function aceptarComoCorrectoPensamientoCritico() {
    $('#hidEstadoLLMPensamientoCritico').val('correcto');
    $('#hidAceptadoManualPensamientoCritico').val('Y');
    var textoHTML = summernotePensamientoCriticoInit
        ? $.trim($('#txtPensamientoCritico').summernote('code') || '')
        : $.trim($('#txtPensamientoCritico').val() || '');
    $('#hidUltimoValidadoPensamientoCritico').val(textoHTML);
    $('#divResultadoLLMPensamientoCritico').hide();
    $('#wrapEditorPensamientoCritico .note-editor').css('border', '2px solid #28a745');
}

function validarPensamientoCriticoConLLM() {
    return validarCampoSummernoteCatedratico({
        editedId: 'hidEditedPensamientoCritico',
        aceptadoId: 'hidAceptadoManualPensamientoCritico',
        textareaId: 'txtPensamientoCritico',
        ultimoValidadoId: 'hidUltimoValidadoPensamientoCritico',
        estadoId: 'hidEstadoLLMPensamientoCritico',
        divResultadoId: 'divResultadoLLMPensamientoCritico',
        nombreCampo: 'PensamientoCritico',
        summernoteInit: function() { return !!summernotePensamientoCriticoInit; },
        mostrarResultado: mostrarResultadoPensamientoCritico
    });
}

function mostrarResultadoRubroEvalEnFila(n, resultado, llamoLLM) {
    var div = $('#divResultadoLLMRubroEval_' + n);
    var inp = $('#txtRubroEval_' + n);
    if (inp.attr('data-aceptado-manual') === 'true') return;

    if (!resultado && !llamoLLM) {
        div.show();
        return;
    }
    if (!resultado) return;

    var color = resultado.estado === 'correcto' ? '#4CAF50' : '#F44336';
    inp.css('border', '2px solid ' + color);
    inp.css('background-color', resultado.estado === 'incorrecto' ? '#ffebee' : '');

    var titulo = resultado.estado === 'correcto' ? 'Texto correcto' : 'Texto incorrecto';
    var html = '<div style="font-size:12px;">' + llmEncabezadoIA();
    html += '<strong style="color:' + color + ';">' + titulo + '</strong>';
    if (resultado.justificacion) {
        html += '<div style="margin-top:8px;"><strong>Retroalimentaci&oacute;n:</strong> ' + resultado.justificacion + '</div>';
    }
    if (resultado.sugerencia && resultado.sugerencia.trim() !== '') {
        var esIncorrectoRubro = resultado.estado === 'incorrecto';
        var labelTextoRubro = esIncorrectoRubro ? 'Corrección:' : 'Sugerencia:';
        var labelAplicarRubro = esIncorrectoRubro ? 'Aplicar corrección' : 'Aplicar sugerencia';
        var colorAplicarRubro = esIncorrectoRubro ? '#4CAF50' : '#2196F3';
        var onAceptarRubro = esIncorrectoRubro ? '' : 'aceptarComoCorrectoRubroEval(' + n + ')';
        html += '<div style="margin-top:8px;"><strong>' + labelTextoRubro + '</strong> ' + resultado.sugerencia + '</div>';
        div.attr('data-sugerencia', resultado.sugerencia);
        html += llmBotonesAceptarAplicar('aplicarSugerenciaRubroEval(' + n + ')', onAceptarRubro, labelAplicarRubro, 'Aceptar actual como correcto', colorAplicarRubro);
    } else if (resultado.estado !== 'correcto' && resultado.estado !== 'incorrecto') {
        html += llmBotonesAceptarAplicar('', 'aceptarComoCorrectoRubroEval(' + n + ')', '', 'Aceptar actual como correcto');
    }
    if (resultado.error) html = '<strong style="color:red;">Error:</strong> ' + resultado.error;
    html += '</div>';
    div.html(html).attr('data-estado', resultado.estado).css('border-left-color', color).show();
    $('#hidEstadoLLMRubroEval_' + n).val(resultado.estado);
}

function aplicarSugerenciaRubroEval(n) {
    var sug = $('#divResultadoLLMRubroEval_' + n).attr('data-sugerencia') || '';
    $('#txtRubroEval_' + n).val(sug).attr('data-ultimo-validado', '').attr('data-aceptado-manual', 'false');
    $('#hidEstadoLLMRubroEval_' + n).val('');
    $('#divResultadoLLMRubroEval_' + n).hide().html('');
    $('#txtRubroEval_' + n).css({ border: '', backgroundColor: '' });
    $('#hidEditedEval_' + n).val('Y');
}

function aceptarComoCorrectoRubroEval(n) {
    var inp = $('#txtRubroEval_' + n);
    inp.attr('data-aceptado-manual', 'true').attr('data-ultimo-validado', inp.val());
    $('#hidEstadoLLMRubroEval_' + n).val('correcto');
    $('#divResultadoLLMRubroEval_' + n).attr('data-estado', 'correcto').hide();
    inp.css({ border: '2px solid #28a745', backgroundColor: '' });
}

function validarTodasActividadesEvalConLLM() {
    var hayErrores = false;
    $('#evalBody tr[id^="trEval_"]').each(function() {
        var n = this.id.replace('trEval_', '');
        if ($('#hidDeleteEval_' + n).val() === 'Y') return true;
        var esNueva = $('#hidNewEval_' + n).length && $('#hidNewEval_' + n).val() === '1';
        var fueEditada = $('#hidEditedEval_' + n).val() === 'Y';
        if (!esNueva && !fueEditada) return true;

        var inp = $('#txtRubroEval_' + n);
        var rubro = $.trim(inp.val());
        if (rubro === '') return true;

        var ultimo = inp.attr('data-ultimo-validado') || '';
        if (rubro !== ultimo && inp.attr('data-aceptado-manual') !== 'true') {
            var resultado = llmAjaxSincrono({ validarOrtografia: true, texto: rubro, contextoCampo: 'Actividad de aprendizaje' });
            mostrarResultadoRubroEvalEnFila(n, resultado, true);
            inp.attr('data-ultimo-validado', rubro);
            if (resultado && resultado.estado === 'incorrecto') hayErrores = true;
        } else {
            mostrarResultadoRubroEvalEnFila(n, null, false);
            if ($('#divResultadoLLMRubroEval_' + n).attr('data-estado') === 'incorrecto') hayErrores = true;
        }
    });
    return !hayErrores;
}

function mostrarResultadoBiblioEvEnFila(n, resultado, llamoLLM) {
    var div = $('#divResultadoLLMBiblio_' + n);
    var txt = $('#txtBiblio_' + n);
    var wrapEditor = $('#wrapEditorBiblio_' + n);
    if (txt.attr('data-aceptado-manual') === 'true') return;

    if (!resultado && !llamoLLM) { div.show(); return; }
    if (!resultado) return;

    var color = '#4CAF50';
    if (resultado.estado === 'puede_mejorarse') color = '#FF9800';
    if (resultado.estado === 'incorrecto') color = '#F44336';

    wrapEditor.removeClass('field-error');
    $('#wrapEditorBiblio_' + n + ' .note-editor').css('border', '2px solid ' + color);
    $('#wrapEditorBiblio_' + n + ' .note-editable').css(
        'background-color',
        resultado.estado === 'incorrecto' ? '#ffebee' : 'white'
    );

    var mensaje = 'Estado: Referencia correcta seg&uacute;n formato Chicago';
    if (resultado.estado === 'puede_mejorarse') mensaje = 'Estado: Referencia puede mejorarse (formato Chicago)';
    if (resultado.estado === 'incorrecto') mensaje = 'Estado: Referencia incorrecta seg&uacute;n formato Chicago';

    var html = '<div style="font-size:12px;">' + llmEncabezadoIABiblio();
    html += '<strong style="color:' + color + ';">' + mensaje + '</strong>';
    if (resultado.justificacion) html += '<div style="margin-top:8px;"><strong>Retroalimentaci&oacute;n:</strong> ' + escapeHtmlTexto(resultado.justificacion) + '</div>';
    if (resultado.sugerencia && resultado.sugerencia !== '') {
        var esIncorrectoBiblio = resultado.estado === 'incorrecto';
        var labelTextoBiblio = esIncorrectoBiblio ? 'Corrección:' : 'Sugerencia:';
        var labelAplicarBiblio = esIncorrectoBiblio ? 'Aplicar corrección' : 'Aplicar sugerencia';
        var colorAplicarBiblio = esIncorrectoBiblio ? '#4CAF50' : '#2196F3';
        var onAceptarBiblio = esIncorrectoBiblio ? '' : 'aceptarComoCorrectaBiblioEv(' + n + ')';
        html += '<div style="margin-top:8px;"><strong>' + labelTextoBiblio + '</strong> '
             + '<span class="biblio-sugerencia-html">' + resultado.sugerencia + '</span></div>';
        div.attr('data-sugerencia', resultado.sugerencia);
        html += llmBotonesAceptarAplicar('aplicarSugerenciaBiblioEv(' + n + ')', onAceptarBiblio, labelAplicarBiblio, 'Aceptar actual como correcta', colorAplicarBiblio);
    } else if (resultado.estado !== 'correcto' && resultado.estado !== 'incorrecto') {
        html += llmBotonesAceptarAplicar('', 'aceptarComoCorrectaBiblioEv(' + n + ')', '', 'Aceptar actual como correcta');
    }
    if (resultado.error) html = '<strong style="color:red;">Error:</strong> ' + resultado.error;
    html += '</div>';
    div.html(html).attr('data-estado', resultado.estado).css('border-left-color', color).show();
    $('#hidEstadoLLMBiblio_' + n).val(resultado.estado);
}

function aplicarSugerenciaBiblioEv(n) {
    var sug = $('#divResultadoLLMBiblio_' + n).attr('data-sugerencia') || '';
    if (typeof fntSetHtmlBiblioEv === 'function') {
        fntSetHtmlBiblioEv(n, sug);
    } else {
        $('#txtBiblio_' + n).val(sug);
    }
    $('#txtBiblio_' + n).attr('data-ultimo-validado', '').attr('data-aceptado-manual', 'false');
    $('#hidEstadoLLMBiblio_' + n).val('');
    $('#divResultadoLLMBiblio_' + n).hide().html('');
    $('#wrapEditorBiblio_' + n + ' .note-editor').css('border', '');
    $('#wrapEditorBiblio_' + n + ' .note-editable').css('background-color', 'white');
    $('#hidEditedBiblio_' + n).val('Y');
}

function aceptarComoCorrectaBiblioEv(n) {
    var txt = $('#txtBiblio_' + n);
    var valorActual = (typeof fntGetHtmlBiblioEv === 'function') ? fntGetHtmlBiblioEv(n) : txt.val();
    txt.attr('data-aceptado-manual', 'true');
    txt.attr('data-ultimo-validado', valorActual);
    $('#hidEstadoLLMBiblio_' + n).val('correcto');
    $('#divResultadoLLMBiblio_' + n).attr('data-estado', 'correcto').hide();
    $('#wrapEditorBiblio_' + n + ' .note-editor').css('border', '2px solid #28a745');
    $('#wrapEditorBiblio_' + n + ' .note-editable').css('background-color', 'white');
}

function validarTodasBibliografiasEvConLLM() {
    var hayErrores = false;
    $('#biblioEvList .biblio-ev-item[id^="liBiblio_"]').each(function() {
        var n = this.id.replace('liBiblio_', '');
        if ($('#hidDeleteBiblio_' + n).val() === 'Y') return true;
        var esNueva = $('#hidNewBiblio_' + n).length && $('#hidNewBiblio_' + n).val() === '1';
        var fueEditada = $('#hidEditedBiblio_' + n).val() === 'Y';
        if (!esNueva && !fueEditada) return true;

        var txt = $('#txtBiblio_' + n);
        var referenciaHtml = (typeof fntGetHtmlBiblioEv === 'function')
            ? fntGetHtmlBiblioEv(n)
            : $.trim(txt.val());
        if (textoPlanoDesdeHtml(referenciaHtml) === '') return true;

        var ultimo = txt.attr('data-ultimo-validado') || '';
        if (referenciaHtml !== ultimo && txt.attr('data-aceptado-manual') !== 'true') {
            var resultado = llmAjaxSincrono({ validarBibliografia: true, referenciaBibliografica: referenciaHtml });
            mostrarResultadoBiblioEvEnFila(n, resultado, true);
            txt.attr('data-ultimo-validado', referenciaHtml);
            if (resultado && resultado.estado === 'incorrecto') hayErrores = true;
        } else {
            mostrarResultadoBiblioEvEnFila(n, null, false);
            if ($('#divResultadoLLMBiblio_' + n).attr('data-estado') === 'incorrecto') hayErrores = true;
        }
    });
    return !hayErrores;
}

function llmResultadosAprendizajeParaAjax() {
    return JSON.stringify(
        (typeof arrResultadosAprendizajeUA !== 'undefined' && arrResultadosAprendizajeUA)
            ? arrResultadosAprendizajeUA
            : []
    );
}

function mostrarResultadoExpEnFila(n, resultado, llamoLLM) {
    var div = $('#divResultadoLLMExp_' + n);
    var txt = $('#txtExp_' + n);
    if (txt.attr('data-aceptado-manual') === 'true') return;

    if (!resultado && !llamoLLM) { div.show(); return; }
    if (!resultado) return;

    var color = '#4CAF50';
    if (resultado.estado === 'puede_mejorarse') color = '#FF9800';
    if (resultado.estado === 'incorrecto') color = '#F44336';

    txt.css('border', '2px solid ' + color);
    txt.css('background-color', resultado.estado === 'incorrecto' ? '#ffebee' : '');

    var mensaje = 'Estado: Experiencia correcta (Vinculada con los resultados de aprendizaje del curso)';
    if (resultado.estado === 'puede_mejorarse') mensaje = 'Estado: Experiencia puede mejorarse';
    if (resultado.estado === 'incorrecto') mensaje = 'Estado: Experiencia incorrecta';

    var html = '<div style="font-size:12px;">' + llmEncabezadoIABiblio();
    html += '<strong style="color:' + color + ';">' + mensaje + '</strong>';
    if (resultado.justificacion) {
        html += '<div style="margin-top:8px;"><strong>Retroalimentaci&oacute;n:</strong> ' + resultado.justificacion + '</div>';
    }
    if (resultado.sugerencia && resultado.sugerencia !== '') {
        var esIncorrectoExp = resultado.estado === 'incorrecto';
        var labelTextoExp = esIncorrectoExp ? 'Corrección:' : 'Sugerencia:';
        var labelAplicarExp = esIncorrectoExp ? 'Aplicar corrección' : 'Aplicar sugerencia';
        var colorAplicarExp = esIncorrectoExp ? '#4CAF50' : '#2196F3';
        var onAceptarExp = esIncorrectoExp ? '' : 'aceptarComoCorrectaExp(' + n + ')';
        html += '<div style="margin-top:8px;"><strong>' + labelTextoExp + '</strong> ' + resultado.sugerencia + '</div>';
        div.attr('data-sugerencia', resultado.sugerencia);
        html += llmBotonesAceptarAplicar('aplicarSugerenciaExp(' + n + ')', onAceptarExp, labelAplicarExp, 'Aceptar actual como correcta', colorAplicarExp);
    } else if (resultado.estado !== 'correcto' && resultado.estado !== 'incorrecto') {
        html += llmBotonesAceptarAplicar('', 'aceptarComoCorrectaExp(' + n + ')', '', 'Aceptar actual como correcta');
    }
    if (resultado.error) html = '<strong style="color:red;">Error:</strong> ' + resultado.error;
    html += '</div>';
    div.html(html).attr('data-estado', resultado.estado).css('border-left-color', color).show();
    $('#hidEstadoLLMExp_' + n).val(resultado.estado);
}

function aplicarSugerenciaExp(n) {
    var sug = $('#divResultadoLLMExp_' + n).attr('data-sugerencia') || '';
    $('#txtExp_' + n).val(sug).attr('data-ultimo-validado', '').attr('data-aceptado-manual', 'false');
    $('#hidEstadoLLMExp_' + n).val('');
    $('#divResultadoLLMExp_' + n).hide().html('');
    $('#txtExp_' + n).css({ border: '', backgroundColor: '' });
    $('#hidEditedExp_' + n).val('Y');
}

function aceptarComoCorrectaExp(n) {
    var txt = $('#txtExp_' + n);
    txt.attr('data-aceptado-manual', 'true').attr('data-ultimo-validado', txt.val());
    $('#hidEstadoLLMExp_' + n).val('correcto');
    $('#divResultadoLLMExp_' + n).attr('data-estado', 'correcto').hide();
    txt.css({ border: '2px solid #28a745', backgroundColor: '' });
}

function validarTodasExperienciasConLLM() {
    var hayErrores = false;
    $('#expList .biblio-ev-item[id^="liExp_"]').each(function() {
        var n = this.id.replace('liExp_', '');
        if ($('#hidDeleteExp_' + n).val() === 'Y') return true;
        var esNueva = $('#hidNewExp_' + n).length && $('#hidNewExp_' + n).val() === '1';
        var fueEditada = $('#hidEditedExp_' + n).val() === 'Y';
        if (!esNueva && !fueEditada) return true;

        var txt = $('#txtExp_' + n);
        var descripcion = $.trim(txt.val());
        if (descripcion === '') return true;

        var ultimo = txt.attr('data-ultimo-validado') || '';
        if (descripcion !== ultimo && txt.attr('data-aceptado-manual') !== 'true') {
            var resultado = llmAjaxSincrono({
                validarExperiencia: true,
                descripcionExperiencia: descripcion,
                resultadosAprendizaje: llmResultadosAprendizajeParaAjax()
            });
            mostrarResultadoExpEnFila(n, resultado, true);
            txt.attr('data-ultimo-validado', descripcion);
            if (resultado && resultado.estado === 'incorrecto') hayErrores = true;
        } else {
            mostrarResultadoExpEnFila(n, null, false);
            if ($('#divResultadoLLMExp_' + n).attr('data-estado') === 'incorrecto') hayErrores = true;
        }
    });
    return !hayErrores;
}

function validarTodosCamposLLMAntesDeGuardar() {
    var haySugerencias = false;
    var okNormas = validarNormasConLLM();
    var okUsoIA = validarUsoIAConLLM();
    var okPensamientoCritico = validarPensamientoCriticoConLLM();
    var okEval = validarTodasActividadesEvalConLLM();
    var okBiblio = validarTodasBibliografiasEvConLLM();
    var okExp = validarTodasExperienciasConLLM();

    if ($('#hidEditedNormas').val() === 'Y') {
        var estadoNormas = $('#hidEstadoLLMNormas').val();
        if (estadoNormas === 'puede_mejorarse') haySugerencias = true;
        if (estadoNormas !== 'correcto') return { ok: false, haySugerencias: haySugerencias };
    }

    if ($('#hidEditedUsoIA').val() === 'Y') {
        var estadoUsoIA = $('#hidEstadoLLMUsoIA').val();
        if (estadoUsoIA === 'puede_mejorarse') haySugerencias = true;
        if (estadoUsoIA !== 'correcto') return { ok: false, haySugerencias: haySugerencias };
    }

    if ($('#hidEditedPensamientoCritico').val() === 'Y') {
        var estadoPensamientoCritico = $('#hidEstadoLLMPensamientoCritico').val();
        if (estadoPensamientoCritico === 'puede_mejorarse') haySugerencias = true;
        if (estadoPensamientoCritico !== 'correcto') return { ok: false, haySugerencias: haySugerencias };
    }

    var errorEval = false;
    $('#evalBody tr[id^="trEval_"]').each(function() {
        var n = this.id.replace('trEval_', '');
        if ($('#hidDeleteEval_' + n).val() === 'Y') return true;
        var esNueva = $('#hidNewEval_' + n).length && $('#hidNewEval_' + n).val() === '1';
        var fueEditada = $('#hidEditedEval_' + n).val() === 'Y';
        if (!esNueva && !fueEditada) return true;
        var estado = $('#hidEstadoLLMRubroEval_' + n).val();
        if (estado !== 'correcto') { errorEval = true; return false; }
    });

    var errorBiblio = false;
    $('#biblioEvList .biblio-ev-item[id^="liBiblio_"]').each(function() {
        var n = this.id.replace('liBiblio_', '');
        if ($('#hidDeleteBiblio_' + n).val() === 'Y') return true;
        var esNueva = $('#hidNewBiblio_' + n).length && $('#hidNewBiblio_' + n).val() === '1';
        var fueEditada = $('#hidEditedBiblio_' + n).val() === 'Y';
        if (!esNueva && !fueEditada) return true;
        var estado = $('#hidEstadoLLMBiblio_' + n).val();
        if (estado === 'puede_mejorarse') haySugerencias = true;
        if (estado !== 'correcto') { errorBiblio = true; return false; }
    });

    var errorExp = false;
    $('#expList .biblio-ev-item[id^="liExp_"]').each(function() {
        var n = this.id.replace('liExp_', '');
        if ($('#hidDeleteExp_' + n).val() === 'Y') return true;
        var esNueva = $('#hidNewExp_' + n).length && $('#hidNewExp_' + n).val() === '1';
        var fueEditada = $('#hidEditedExp_' + n).val() === 'Y';
        if (!esNueva && !fueEditada) return true;
        var estado = $('#hidEstadoLLMExp_' + n).val();
        if (estado === 'puede_mejorarse') haySugerencias = true;
        if (estado !== 'correcto') { errorExp = true; return false; }
    });

    if (!okNormas || !okUsoIA || !okPensamientoCritico || !okEval || !okBiblio || !okExp || errorEval || errorBiblio || errorExp) {
        return { ok: false, haySugerencias: haySugerencias };
    }
    return { ok: true, haySugerencias: haySugerencias };
}

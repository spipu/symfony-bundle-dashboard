// spipu-dashboard/dashboard-configure.js

class DashboardConfigure {
    constructor(
        dashboardName,
        dashboardContent,
        sources,
        periods,
        types,
        saveRouter
    )
    {
        this.dashboardName = dashboardName;
        this.dashboardContent = dashboardContent;
        this.sources = sources;
        this.periods = periods;
        this.types = types;
        this.saveRouter = saveRouter
        this.templates = [];
        this.currentRowId = 0;

        this.needSave = false;
        this.dashboardResetSave();

        this.rowMoveEvent = {};
        this.rowMoveEventInit();

        this.widgetMoveEvent = {};
        this.widgetMoveEventInit();

        this.currentWidget = {'action': 'none'};
        this.currentWidgetSet();

        this.dashboardInit();
    }

    dashboardInit()
    {
        this.loadTemplates();

        if (this.dashboardName === 'default') {
            $('#dashboard-name-form').hide();
        } else {
            $('#dashboard-name-noform').hide();
            let inputName = $('#dashboard-name-field');
            inputName.val(this.dashboardName);
            inputName.on('change', function () {
                this.dashboardName = inputName.val();
            }.bind(this));
            inputName.on('keyup', function () {
                this.dashboardName = inputName.val();
            }.bind(this));
        }

        $('#dashboard-save').on('click', this.dashboardSave.bind(this));
        $('#dashboard-back').on('click', this.dashboardBack.bind(this));
        $('#dashboard-add-row-button-add').show().on('click', this.rowToggleSelect.bind(this));
        $('#dashboard-add-row-button-cancel').hide().on('click', this.rowToggleSelect.bind(this));
        $('#dashboard-add-row-select').hide();
        $('#dashboard-add-row-select-3').on('click', function () {
            this.rowAddManual(3);
        }.bind(this));
        $('#dashboard-add-row-select-4').on('click', function () {
            this.rowAddManual(4);
        }.bind(this));

        $('.dashboard-row')
            .on('dragover', this.rowMoveEventDragOver.bind(this))
            .on('drop', this.rowMoveEventDrop.bind(this))
        ;

        this.widgetFormInit();
        this.dashboardDisplayAll();
        this.dashboardResetSave();
    }

    dashboardNeedSave(purpose)
    {
        this.needSave = true;
        $('#dashboard-save').show();
    }

    dashboardResetSave()
    {
        this.needSave = false;
        $('#dashboard-save').hide();
    }

    dashboardBack()
    {
        if (!this.needSave) {
            return true;
        }

        let popup = window.ConfirmPopups.create(
            window.translator.trans('spipu.dashboard.label.confirm_ignore_save'),
            window.translator.trans('spipu.dashboard.ui.action.confirm'),
            'check',
            'warning',
            false,
        );

        popup.addCallbackConfirm(
            function () {
                popup.close();
                window.location = $('#dashboard-back').attr('href');
            }.bind(this)
        );

        return false;
    }

    dashboardDisplayAll()
    {
        let mainDisplay = $('#dashboard-main-display');

        mainDisplay.removeClass('d-none').hide();

        $('#dashboard-rows').html('');

        let rowsNotEmpty = $('#dashboard-rows-not-empty');
        let rowsEmpty = $('#dashboard-rows-empty');

        if (this.dashboardContent.rows.length === 0) {
            rowsNotEmpty.hide();
            rowsEmpty.show();
            mainDisplay.show();
            return;
        }

        rowsNotEmpty.show();
        rowsEmpty.hide();

        this.currentRowId = 0;
        for (let rowKey in this.dashboardContent.rows) {
            rowKey = parseInt(rowKey);
            let rowId = this.currentRowId;
            this.dashboardContent.rows[rowKey].id = rowId;
            this.dashboardContent.rows[rowKey].width = 12 / this.dashboardContent.rows[rowKey].nbCol;
            this.rowAdd(this.dashboardContent.rows[rowKey].nbCol, true);
            $('#dashboard-row-' + rowId + '-title').val(this.dashboardContent.rows[rowKey].title);
            for (let colId in this.dashboardContent.rows[rowKey].cols) {
                colId = parseInt(colId);
                this.dashboardContent.rows[rowKey].cols[colId].id = colId;
                for (let widgetId in this.dashboardContent.rows[rowKey].cols[colId].widgets) {
                    if (this.widgetGetConfig(rowId, colId, widgetId) === null) {
                        continue;
                    }
                    widgetId = parseInt(widgetId);
                    this.currentWidgetSet(rowId, colId, widgetId);
                    this.dashboardContent.rows[rowKey].cols[colId].widgets[widgetId].width = 1;
                    this.dashboardContent.rows[rowKey].cols[colId].widgets[widgetId].height = 2;
                    this.widgetSave();
                }
            }
        }

        mainDisplay.show();
    }

    loadTemplates()
    {
        this.loadTemplate('widget');
        this.loadTemplate('widget-empty');
        this.loadTemplate('col');
        this.loadTemplate('row');
    }

    loadTemplate(code)
    {
        let source = $('#dashboard-template-' + code);

        this.templates[code] = source.clone();
        this.templates[code].attr('id', '');

        source.remove();
    }

    getTemplate(code)
    {
        return this.templates[code].clone();
    }

    rowToggleSelect()
    {
        $('#dashboard-add-row-button-add').toggle();
        $('#dashboard-add-row-button-cancel').toggle();
        $('#dashboard-add-row-select').toggle();
    }

    rowAddManual(nbCol)
    {
        this.rowAdd(nbCol, false);
        this.dashboardNeedSave('row add');
    }

    rowAdd(nbCol, onlyAddHtml)
    {
        if (onlyAddHtml === undefined) {
            onlyAddHtml = false;
        }

        let width = 12 / nbCol;
        let htmlRow = this.getTemplate('row');
        let rowId = this.currentRowId;

        let contentRow = {
            'id': rowId,
            'title': '',
            'nbCol': nbCol,
            'width': width,
            'cols': []
        };

        htmlRow.attr('id', 'dashboard-row-' + rowId);
        htmlRow.data('row-id', rowId);
        htmlRow.find('label').attr('for', 'dashboard-row-' + rowId + '-title');
        htmlRow.find('input').attr('id', 'dashboard-row-' + rowId + '-title');

        htmlRow.on('drag', this.rowMoveEventDrag.bind(this));
        htmlRow.on('dragover', this.rowMoveEventDragOver.bind(this));
        htmlRow.on('drop', this.rowMoveEventDrop.bind(this));

        htmlRow.find('.dashboard-icon-delete')
            .attr('id', 'dashboard-row-' + rowId + '-delete')
            .on('mouseenter', function () {
                this.rowActionEnter(rowId, 'delete', 'danger');
            }.bind(this))
            .on('mouseleave', function () {
                this.rowActionLeave(rowId, 'delete', 'danger');
            }.bind(this))
            .on('click', function () {
                this.rowDelete(rowId);
            }.bind(this))
        ;

        htmlRow.find('.dashboard-icon-clone')
            .attr('id', 'dashboard-row-' + rowId + '-clone')
            .on('mouseenter', function () {
                this.rowActionEnter(rowId, 'clone', 'primary');
            }.bind(this))
            .on('mouseleave', function () {
                this.rowActionLeave(rowId, 'clone', 'primary');
            }.bind(this))
            .on('click', function () {
                this.rowClone(rowId);
            }.bind(this))
        ;

        htmlRow.find('.dashboard-icon-move')
            .attr('id', 'dashboard-row-' + rowId + '-move')
            .on('mouseenter', function () {
                this.rowActionEnterMove(rowId);
            }.bind(this))
            .on('mouseleave', function () {
                this.rowActionLeaveMove(rowId);
            }.bind(this))
        ;

        htmlRow.find('input').on('change', function () {
            this.updateRowTitle(rowId);
        }.bind(this));
        htmlRow.find('input').on('keyup', function () {
            this.updateRowTitle(rowId);
        }.bind(this));

        for (let colId = 0; colId < nbCol; colId++) {
            let contentCol = {
                'id': colId,
                'widgets': []
            }
            contentRow.cols.push(contentCol);

            let htmlCol = this.getTemplate('col');
            htmlCol.addClass('col-' + width);
            htmlCol.attr('id', 'dashboard-row-' + rowId + '-col-' + colId);
            htmlCol.data('row-id', rowId);
            htmlCol.data('col-id', colId);

            htmlCol.on('drag', this.widgetMoveEventDrag.bind(this));
            htmlCol.on('dragover', this.widgetMoveEventDragOver.bind(this));
            htmlCol.on('drop', this.widgetMoveEventDrop.bind(this));

            let htmlWidgetEmpty = this.widgetPrepareEmptyHtml(rowId, colId, 0, 2);
            htmlCol.append(htmlWidgetEmpty);

            htmlRow.find('.dashboard-row-cols').append(htmlCol);
        }

        $('#dashboard-rows').append(htmlRow);

        if (!onlyAddHtml) {
            this.dashboardContent.rows.push(contentRow);
            this.rowToggleSelect();
            $('#dashboard-rows-not-empty').show();
            $('#dashboard-rows-empty').hide();
        }

        this.currentRowId++;
    }

    widgetPrepareEmptyHtml(rowId, colId, widgetId, height)
    {
        let htmlWidgetEmpty = this.getTemplate('widget-empty');

        htmlWidgetEmpty.attr('id', this.widgetGetId(rowId, colId, widgetId));
        htmlWidgetEmpty.addClass(this.getWidgetCssHeight(height));
        htmlWidgetEmpty.on('click', function () {
            this.currentWidgetSet(rowId, colId, widgetId);
        }.bind(this));

        return htmlWidgetEmpty;
    }

    getWidgetCssHeight(height)
    {
        return (height > 1) ? 'widget-height-double' : 'widget-height-simple';
    }

    updateRowTitle(rowId)
    {
        let rowKey = this.getContentRowKey(rowId);
        if (rowKey === null) {
            return;
        }

        this.dashboardContent.rows[rowKey]['title'] = $('#dashboard-row-' + rowId + '-title').first().val();
        this.dashboardNeedSave('row title');
    }

    widgetFormInit()
    {
        $('#dashboard-configure-widget-field-source').on('change', this.widgetFormSelectSourceChange.bind(this));
        $('#dashboard-configure-widget-field-type-selector').find('div.type-selector').on('click', this.widgetFormSelectType.bind(this));
        $('#dashboard-configure-widget-field-width-selector').find('div.width-selector').on('click', this.widgetFormSelectWidth.bind(this));
        $('#dashboard-configure-widget-field-height-selector').find('div.height-selector').on('click', this.widgetFormSelectHeight.bind(this));
        $('#dashboard-configure-widget-save').on('click', this.widgetSaveForm.bind(this));

        this.widgetFormHide();
    }

    currentWidgetSet(rowId, colId, widgetId)
    {
        if (this.currentWidget.action !== 'none') {
            this.currentWidgetUnsetBorderStyle();
        }

        if (rowId === undefined) {
            this.currentWidget = {'action': 'none'};
            this.widgetFormHide();
            return;
        }

        let rowKey = this.getContentRowKey(rowId)
        if (rowKey === null) {
            return;
        }

        let action = 'update';
        let nbCols = this.dashboardContent.rows[rowKey].nbCol;
        let minWidth = 1;
        let maxWidth = nbCols - colId;
        let maxHeight = 2;
        let config = this.widgetGetConfig(rowId, colId, widgetId);
        let otherConfig = this.widgetGetConfig(rowId, colId, 1 - widgetId);

        if (otherConfig || widgetId === 1) {
            maxHeight = 1;
        }

        if (otherConfig && widgetId === 1) {
            minWidth = otherConfig.width;
            maxWidth = otherConfig.width;
        }

        if (minWidth < maxWidth) {
            maxWidth = Math.min(maxWidth, this.widgetGetAvailableWidth(rowId, colId));
        }

        if (config === null) {
            action = 'create';
            config = {
                'id': Math.random().toString(16).slice(2),
                'source': Object.keys(this.sources)[0],
                'type': Object.keys(this.types)[0],
                'period': Object.keys(this.periods)[0],
                'width': minWidth,
                'height': maxHeight,
                'filters': []
            }

            if (this.sources[config.source].specificDisplay) {
                config.type = 'specific';
            }
        }

        if (config.period === null) {
            config.period = Object.keys(this.periods)[0];
        }

        this.currentWidget = {
            'action': action,
            'rowId': rowId,
            'colId': colId,
            'widgetId': widgetId,
            'params': {
                'nbCol': nbCols,
                'minWidth': minWidth,
                'maxWidth': maxWidth,
                'maxHeight': maxHeight
            },
            'config': config
        }

        this.currentWidgetSetBorderStyle();
        this.widgetFormShow();
    }

    widgetGetAvailableWidth(rowId, colId)
    {
        let rowKey = this.getContentRowKey(rowId)
        if (rowKey === null) {
            return 0;
        }
        let nbCols = this.dashboardContent.rows[rowKey].nbCol;

        for (let col = colId + 1; col < nbCols; col++) {
            if (this.widgetGetConfig(rowId, col, 0) || this.widgetGetConfig(rowId, col, 1)) {
                return (col - colId);
            }
        }

        return (nbCols - colId);
    }

    widgetGetColWidth(rowId, colId)
    {
        let widget0 = this.widgetGetConfig(rowId, colId, 0);
        let widget1 = this.widgetGetConfig(rowId, colId, 1);

        if (widget0 === null && widget1 === null) {
            return this.widgetGetAvailableWidth(rowId, colId);
        }

        return widget0 ? widget0.width : widget1.width;
    }

    widgetDisplay()
    {
        let rowId = this.currentWidget.rowId;
        let colId = this.currentWidget.colId;
        let widgetId = this.currentWidget.widgetId;

        let oldConfig = this.widgetGetConfig(rowId, colId, widgetId);
        let newConfig = this.currentWidget.config;

        let oldOtherConfig = this.widgetGetConfig(rowId, colId, 1 - widgetId);

        let newWidth = newConfig.width;
        let newHeight = newConfig.height;

        let oldWidth = 1;
        let oldHeight = 2;

        if (oldConfig) {
            oldWidth = oldConfig.width;
            oldHeight = oldConfig.height;
        } else {
            if (oldOtherConfig) {
                oldWidth = oldOtherConfig.width;
                oldHeight = 1;
            }
        }

        let title = this.sources[newConfig.source].label;

        if (newConfig.period) {
            title += ' - ' + this.periods[newConfig.period].label;
        }

        this.widgetSetConfig(rowId, colId, widgetId, newConfig);

        let htmlWidgetId = this.widgetGetId(rowId, colId, widgetId);

        let htmlWidget = this.getTemplate('widget');
        htmlWidget.attr('id', htmlWidgetId);
        htmlWidget.addClass(this.getWidgetCssHeight(newHeight));
        htmlWidget.find('.type-icon').hide();
        htmlWidget.find('.type-icon[data-type=' + newConfig.type + ']').show();
        if (newConfig.type === 'specific') {
            htmlWidget.find('.type-icon[data-type=specific]').addClass('fa-' + this.sources[newConfig.source].specificDisplay);
        }
        htmlWidget.find('.widget-title').html(title);

        htmlWidget.on('click', function () {
            this.currentWidgetSet(rowId, colId, widgetId);
        }.bind(this));

        htmlWidget.find('.dashboard-icon-delete')
            .on('mouseenter', function () {
                this.widgetActionEnter(rowId, colId, widgetId, 'delete', 'danger');
            }.bind(this))
            .on('mouseleave', function () {
                this.widgetActionLeave(rowId, colId, widgetId, 'delete', 'danger');
            }.bind(this))
            .on('click', function (event) {
                    event.stopPropagation();
                    this.widgetDelete(rowId, colId, widgetId);
                }.bind(this)
            )
        ;

        if (widgetId > 0) {
            htmlWidget.find('.dashboard-icon-move').remove();
        } else {
            htmlWidget.find('.dashboard-icon-move')
                .on('mouseenter', function () {
                    this.widgetActionEnterMove(rowId, colId, widgetId);
                }.bind(this))
                .on('mouseleave', function () {
                    this.widgetActionLeaveMove(rowId, colId, widgetId);
                }.bind(this))
            ;
        }

        $('#' + htmlWidgetId).replaceWith(htmlWidget);

        if (widgetId === 0) {
            if (oldHeight < newHeight) {
                $('#' + this.widgetGetId(rowId, colId, widgetId + 1)).remove();
            }

            if (newHeight < oldHeight && widgetId === 0) {
                let htmlWidgetEmpty = this.widgetPrepareEmptyHtml(rowId, colId, widgetId + 1,);
                $('#dashboard-row-' + rowId + '-col-' + colId).append(htmlWidgetEmpty);
            }

            if (oldWidth < newWidth) {
                for (let col = colId + oldWidth; col < colId + newWidth; col++) {
                    $('#dashboard-row-' + rowId + '-col-' + col).hide();
                }
            }
            if (oldWidth > newWidth) {
                for (let col = colId + newWidth; col < colId + oldWidth; col++) {
                    $('#dashboard-row-' + rowId + '-col-' + col).show();
                }
            }
            if (oldWidth !== newWidth) {
                let colWidth = this.dashboardContent.rows[this.getContentRowKey(rowId)].width;
                $('#dashboard-row-' + rowId + '-col-' + colId)
                    .removeClass('col-' + (colWidth * oldWidth))
                    .addClass('col-' + (colWidth * newWidth))
                ;
            }

            if (oldOtherConfig) {
                oldOtherConfig.width = newWidth;
                this.widgetSetConfig(rowId, colId, 1, oldOtherConfig);
            }
        }
    }

    currentWidgetUnsetBorderStyle()
    {
        let widget = $('#' + this.widgetGetId(this.currentWidget.rowId, this.currentWidget.colId, this.currentWidget.widgetId));
        if (!widget) {
            return;
        }

        widget.removeClass('widget-item-selected');

    }

    currentWidgetSetBorderStyle()
    {
        this.currentWidgetUnsetBorderStyle();
        let widget = $('#' + this.widgetGetId(this.currentWidget.rowId, this.currentWidget.colId, this.currentWidget.widgetId));
        if (!widget) {
            return;
        }

        widget.addClass('widget-item-selected');
    }

    widgetFormHide()
    {
        $('#dashboard-configure-widget-empty').show();
        $('#dashboard-configure-widget-form').hide();
    }

    widgetFormShow()
    {
        if (this.currentWidget.params.nbCol === 3) {
            $('#dashboard-configure-widget-field-width-selector-3').show();
            $('#dashboard-configure-widget-field-width-selector-4').hide();
        } else {
            $('#dashboard-configure-widget-field-width-selector-4').show();
            $('#dashboard-configure-widget-field-width-selector-3').hide();
        }

        let widthSelectors = $('#dashboard-configure-widget-field-width-selector .width-selector');
        widthSelectors.each(
            function (key) {
                let widthSelector = $(widthSelectors[key]);
                let widthValue = widthSelector.data('value');
                if (widthValue < this.currentWidget.params.minWidth || widthValue > this.currentWidget.params.maxWidth) {
                    widthSelector.hide();
                } else {
                    widthSelector.show();
                }
            }.bind(this)
        );
        if (this.currentWidget.params.minWidth === this.currentWidget.params.maxWidth) {
            $('#dashboard-configure-widget-fieldset-width').hide();
        } else {
            $('#dashboard-configure-widget-fieldset-width').show();
        }
        this.widgetFormConfigureFilters(this.currentWidget.config.source);

        this.widgetFormHiddenIdValue(this.currentWidget.config.id);
        this.widgetFormSelectSourceValue(this.currentWidget.config.source);
        this.widgetFormSelectTypeValue(this.currentWidget.config.type);
        this.widgetFormSelectPeriodValue(this.currentWidget.config.period);
        this.widgetFormSelectWidthValue(this.currentWidget.config.width);
        this.widgetFormSelectHeightValue(this.currentWidget.config.height);
        this.widgetFormFiltersValue(this.currentWidget.config.filters);

        $('#dashboard-configure-widget-form').show();
        $('#dashboard-configure-widget-empty').hide();
    }

    widgetFormConfigureFilters(sourceName)
    {
        let source = this.sources[sourceName];
        if (!source) {
            return;
        }
        let form = $('#dashboard-configure-widget-filters-form');
        form.empty();
        for (let filterKey of Object.keys(source.filters)) {
            let filter = source.filters[filterKey];
            let formGroup = $('<div>').addClass('mb-3');
            let formLabel = window.translator.trans('spipu.dashboard.configure.field_filters') + ' - ' + filter.name;


            let input = $(`<select id="dashboard-configure-widget-filters-field-${filterKey}" >`).addClass('form-select');
            if (filter.multiple) {
                input.attr('multiple', true);
            } else {
                input.append('<option value=""> - - - </option>');
            }
            for (let optionKey of Object.keys(filter.options)) {
                let option = $(`<option value="${optionKey}">`).text(filter.options[optionKey]);
                input.append(option);
            }

            formGroup.append($(`<label for="dashboard-configure-widget-filters-field-${filterKey}">`).text(formLabel));
            formGroup.append(input);
            form.append(formGroup);
        }
    }

    widgetFormHiddenIdValue(value)
    {
        $('#dashboard-configure-widget-field-id').val(value);
    }

    widgetFormSelectSourceValue(value)
    {
        $('#dashboard-configure-widget-field-source').val(value);
        this.widgetFormSelectSourceChange();
    }

    widgetFormSelectSourceChange()
    {
        let value = $('#dashboard-configure-widget-field-source').val();

        let needPeriod = false;
        let specificDisplay = null;
        if (this.sources[value]) {
            needPeriod = (this.sources[value].needPeriod === 1);
            specificDisplay = (this.sources[value].specificDisplay);
        }

        if (needPeriod) {
            $('#dashboard-configure-widget-fieldset-period').show();
        } else {
            $('#dashboard-configure-widget-fieldset-period').hide();
        }

        let hasFilters = false;
        if (this.sources[value]) {
            hasFilters = !Array.isArray(this.sources[value].filters)
        }

        if (hasFilters) {
            this.widgetFormConfigureFilters(value);
            $('#dashboard-configure-widget-fieldset-filters').show();
        } else {
            $('#dashboard-configure-widget-fieldset-filters').hide();
        }

        if (specificDisplay) {
            $("#type-specific .type-selector").html('<i class="fa fa-2x fa-' + specificDisplay + '"></i>');
            $("#type-specific").show();
        } else {
            $("#type-specific .type-selector").html('');
            $("#type-specific").hide();
        }

        let availableTypes = (this.sources[value] && this.sources[value].availableTypes) || [];

        let previousType = $('#dashboard-configure-widget-field-type').val();
        for (let type in this.types) {
            if (
                (this.types[type].height > this.currentWidget.params.maxHeight)
                || (availableTypes.indexOf(type) === -1)
            ) {
                $('#type-' + type).hide();
                if (previousType === type) {
                    this.widgetFormSelectTypeValue(availableTypes[0] || 'value_single');
                }
            } else {
                $('#type-' + type).show();
            }
        }
    }

    widgetFormSelectPeriodValue(value)
    {
        $('#dashboard-configure-widget-field-period').val(value);
    }

    widgetFormSelectTypeValue(value)
    {
        $('#dashboard-configure-widget-field-type').val(value);

        $('#dashboard-configure-widget-field-type-selector div.type-selector')
            .removeClass('selected');

        let singleHeight = true;
        if (value) {
            $('#dashboard-configure-widget-field-type-selector div.type-selector[data-value=' + value + ']')
                .addClass('selected');
            singleHeight = (this.types[value].height === 1);
        }
        if (this.currentWidget.params.maxHeight === 1) {
            singleHeight = false;
        }

        if (singleHeight) {
            $('#dashboard-configure-widget-fieldset-height').show();
        } else {
            this.widgetFormSelectHeightValue(this.currentWidget.params.maxHeight);
            $('#dashboard-configure-widget-fieldset-height').hide();
        }
    }

    widgetFormSelectWidthValue(value)
    {
        $('#dashboard-configure-widget-field-width').val(value);

        $('#dashboard-configure-widget-field-width-selector div.width-selector')
            .removeClass('selected');

        if (value) {
            $('#dashboard-configure-widget-field-width-selector div.width-selector[data-value=' + value + ']')
                .addClass('selected');
        }
    }

    widgetFormSelectHeightValue(value)
    {
        $('#dashboard-configure-widget-field-height').val(value);

        $('#dashboard-configure-widget-field-height-selector div.height-selector')
            .removeClass('selected');

        if (value) {
            $('#dashboard-configure-widget-field-height-selector div.height-selector[data-value=' + value + ']')
                .addClass('selected');
        }
    }

    widgetFormFiltersValue(value)
    {
        if(!value){
            return;
        }
        let source = this.sources[this.currentWidget.config.source];
        if (!source) {
            return;
        }
        for (let filterKey of Object.keys(source.filters)) {
            if (!value[filterKey]) {
                continue;
            }
            $(`#dashboard-configure-widget-filters-field-${filterKey}`).val(value[filterKey]);
        }
    }

    widgetFormSelectType(event)
    {
        let selector = $(event.target).closest('div.type-selector');
        this.widgetFormSelectTypeValue(selector.data('value'));
    }

    widgetFormSelectWidth(event)
    {
        let selector = $(event.target).closest('div.width-selector');
        this.widgetFormSelectWidthValue(selector.data('value'))
    }

    widgetFormSelectHeight(event)
    {
        let selector = $(event.target).closest('div.height-selector');
        this.widgetFormSelectHeightValue(selector.data('value'));
    }

    rowActionEnterMove(rowId)
    {
        this.rowActionEnter(rowId, 'move', 'success');
    }

    rowActionEnter(rowId, action, style)
    {
        $('#dashboard-row-' + rowId + ' div.card:first').addClass('border-' + style);

        $('#dashboard-row-' + rowId + '-' + action)
            .removeClass('border-secondary')
            .removeClass('text-secondary')
            .addClass('border-' + style)
            .addClass('text-' + style)
        ;

        if (action === 'move') {
            $('#dashboard-row-' + rowId).attr('draggable', true);
        }
    }

    rowActionLeaveMove(rowId)
    {
        this.rowActionLeave(rowId, 'move', 'success');
    }

    rowActionLeave(rowId, action, style)
    {
        $('#dashboard-row-' + rowId + ' div.card:first').removeClass('border-' + style);

        $('#dashboard-row-' + rowId + '-' + action)
            .removeClass('border-' + style)
            .removeClass('text-' + style)
            .addClass('border-secondary')
            .addClass('text-secondary')
        ;

        if (action === 'move') {
            $('#dashboard-row-' + rowId).attr('draggable', false);
        }
    }

    widgetGetId(rowId, colId, widgetId)
    {
        return 'dashboard-row-' + rowId + '-col-' + colId + '-widget-' + widgetId;
    }

    widgetActionEnterMove(rowId, colId, widgetId)
    {
        this.widgetActionEnter(rowId, colId, widgetId, 'move', 'success');
    }

    widgetActionEnter(rowId, colId, widgetId, action, style)
    {
        this.widgetActionEnterPart(
            this.widgetGetId(rowId, colId, widgetId),
            action,
            style
        );

        if (action === 'move' && widgetId === 0) {
            $('#dashboard-row-' + rowId + '-col-' + colId).attr('draggable', true);

            this.widgetActionEnterPart(
                this.widgetGetId(rowId, colId, 1),
                action,
                style
            );
        }
    }

    widgetActionEnterPart(widgetHtmlId, action, style)
    {
        $('#' + widgetHtmlId + ' div.card:first').addClass('border-' + style);

        $('#' + widgetHtmlId + ' .dashboard-icon-' + action)
            .removeClass('border-secondary')
            .removeClass('text-secondary')
            .addClass('border-' + style)
            .addClass('text-' + style)
        ;
    }

    widgetActionLeaveMove(rowId, colId, widgetId)
    {
        this.widgetActionLeave(rowId, colId, widgetId, 'move', 'success');
    }

    widgetActionLeave(rowId, colId, widgetId, action, style)
    {
        this.widgetActionLeavePart(
            this.widgetGetId(rowId, colId, widgetId),
            action,
            style
        );

        if (action === 'move' && widgetId === 0) {
            $('#dashboard-row-' + rowId + '-col-' + colId).attr('draggable', false);

            this.widgetActionLeavePart(
                this.widgetGetId(rowId, colId, 1),
                action,
                style
            );
        }
    }

    widgetActionLeavePart(widgetHtmlId, action, style)
    {
        $('#' + widgetHtmlId + ' div.card:first').removeClass('border-' + style);

        $('#' + widgetHtmlId + ' .dashboard-icon-' + action)
            .removeClass('border-' + style)
            .removeClass('text-' + style)
            .addClass('border-secondary')
            .addClass('text-secondary')
        ;
    }

    widgetDelete(rowId, colId, widgetId)
    {
        this.currentWidgetSet();

        let popup = window.ConfirmPopups.create(
            window.translator.trans('spipu.dashboard.label.confirm_delete_widget'),
            window.translator.trans('spipu.dashboard.ui.action.confirm'),
            'trash',
            'danger',
            false,
        );

        popup.addCallbackConfirm(
            function () {
                popup.close();
                this.widgetDeleteConfirm(rowId, colId, widgetId);
            }.bind(this)
        );

        popup.addCallbackCancel(
            function () {
                this.widgetActionLeave(rowId, colId, widgetId, 'delete', 'danger');
            }.bind(this)
        );

        setTimeout(function () {
            this.widgetActionEnter(rowId, colId, widgetId, 'delete', 'danger');
        }.bind(this), 0);
    }

    widgetDeleteConfirm(rowId, colId, widgetId)
    {
        this.widgetSetConfig(rowId, colId, widgetId, null);
        this.dashboardDisplayAll();
        this.dashboardNeedSave('widget delete');
    }

    widgetMoveEventInit()
    {
        this.widgetMoveEvent = {
            'action': 'none',
            'dragging': null,
            'draggingRowId': null,
            'draggingColId': null,
            'draggedOver': null,
            'draggedOverColId': null
        };
    }

    widgetMoveEventGetCol(event)
    {
        let target = $(event.target);
        if (target.hasClass('dashboard-col')) {
            return target;
        }

        return target.closest('div.dashboard-col')
    }

    widgetMoveEventDrag(event)
    {
        event.preventDefault();

        this.widgetMoveEventInit();

        this.widgetMoveEvent.dragging = this.widgetMoveEventGetCol(event);
        this.widgetMoveEvent.draggingRowId = parseInt(this.widgetMoveEvent.dragging.data('row-id'));
        this.widgetMoveEvent.draggingColId = parseInt(this.widgetMoveEvent.dragging.data('col-id'));
    }

    widgetMoveEventDragOver(event)
    {
        event.preventDefault();

        if (!this.widgetMoveEvent.dragging) {
            return;
        }
        this.widgetActionEnterMove(this.widgetMoveEvent.draggingRowId, this.widgetMoveEvent.draggingColId, 0);

        let target = this.widgetMoveEventGetCol(event);
        let targetRowId = parseInt(target.data('row-id'))
        let targetColId = parseInt(target.data('col-id'))

        this.widgetMoveEvent.action = 'none';
        this.widgetMoveEvent.draggedOver = null;
        $('div.dashboard-col').removeClass('dashboard-col-dragged-over-before').removeClass('dashboard-col-dragged-over-after');

        if (this.widgetMoveEvent.draggingRowId !== targetRowId) {
            return;
        }

        if (this.widgetMoveEvent.draggingColId === targetColId) {
            return;
        }

        let posMiddle = parseInt(target.offset().left + 0.5 * target.width());
        let posCurrent = event.pageX;

        this.widgetMoveEvent.action = ((posMiddle < posCurrent) ? 'after' : 'before');
        this.widgetMoveEvent.draggedOver = target;
        this.widgetMoveEvent.draggedOverColId = targetColId;
        this.widgetMoveEvent.draggedOver.addClass('dashboard-col-dragged-over-' + this.widgetMoveEvent.action);
    }

    widgetMoveEventDrop(event)
    {
        event.preventDefault();

        $('div.dashboard-col').removeClass('dashboard-col-dragged-over-before').removeClass('dashboard-col-dragged-over-after');

        if (this.widgetMoveEvent.dragging) {
            this.widgetActionLeaveMove(this.widgetMoveEvent.draggingRowId, this.widgetMoveEvent.draggingColId, 0);
            if (this.widgetMoveEvent.action !== 'none') {
                let rowId = this.widgetMoveEvent.draggingRowId;
                let rowKey = this.getContentRowKey(rowId);
                if (rowKey === null) {
                    return;
                }

                let action = this.widgetMoveEvent.action;
                let srcColId = this.widgetMoveEvent.draggingColId;
                let dstColId = this.widgetMoveEvent.draggedOverColId;
                let srcColWidth = this.widgetGetColWidth(rowId, srcColId);
                let dstColWidth = this.widgetGetColWidth(rowId, dstColId);

                let cols = [];
                for (let currentColId in this.dashboardContent.rows[rowKey].cols) {
                    currentColId = parseInt(currentColId);
                    if (currentColId >= srcColId && currentColId <= srcColId + srcColWidth - 1) {
                        continue;
                    }

                    if (currentColId === dstColId && action === 'before') {
                        for (let srcColDelta = 0; srcColDelta < srcColWidth; srcColDelta++) {
                            cols.push(this.cloneObject(this.dashboardContent.rows[rowKey].cols[srcColId + srcColDelta]));
                        }
                    }

                    cols.push(this.cloneObject(this.dashboardContent.rows[rowKey].cols[currentColId]));

                    if (currentColId === dstColId + dstColWidth - 1 && action === 'after') {
                        for (let srcColDelta = 0; srcColDelta < srcColWidth; srcColDelta++) {
                            cols.push(this.cloneObject(this.dashboardContent.rows[rowKey].cols[srcColId + srcColDelta]));
                        }
                    }
                }

                this.dashboardContent.rows[rowKey].cols = cols;
                this.dashboardDisplayAll();
                this.dashboardNeedSave('widget move');
            }
        }

        this.widgetMoveEventInit();
    }

    widgetGetConfig(rowId, colId, widgetId)
    {
        let rowKey = this.getContentRowKey(rowId)
        if (rowKey === null) {
            return null;
        }

        let config = null;

        try {
            config = this.dashboardContent.rows[rowKey].cols[colId].widgets[widgetId];
        } catch (e) {
            return null;
        }

        return config === undefined ? null : config;
    }

    widgetSetConfig(rowId, colId, widgetId, config)
    {
        let rowKey = this.getContentRowKey(rowId)
        if (rowKey === null) {
            return null;
        }

        this.dashboardContent.rows[rowKey].cols[colId].widgets[widgetId] = config;
    }

    widgetSaveForm()
    {
        this.widgetSave();
        this.dashboardNeedSave('widget save');
    }

    widgetSave()
    {
        let filters = {};
        let source = this.sources[this.currentWidget.config.source];
        if (!source) {
            return;
        }
        for (let filterKey of Object.keys(source.filters)) {
            filters[filterKey] = $(`#dashboard-configure-widget-filters-field-${filterKey}`).val();
        }

        this.currentWidget.config = {
            'id': $('#dashboard-configure-widget-field-id').val(),
            'source': $('#dashboard-configure-widget-field-source').val(),
            'type': $('#dashboard-configure-widget-field-type').val(),
            'period': $('#dashboard-configure-widget-field-period').val(),
            'width': parseInt($('#dashboard-configure-widget-field-width').val()),
            'height': parseInt($('#dashboard-configure-widget-field-height').val()),
            'filters': filters
        };

        let rowKey = this.getContentRowKey(this.currentWidget.rowId)
        if (
            rowKey === null
            || this.sources[this.currentWidget.config.source] === undefined
            || this.types[this.currentWidget.config.type] === undefined
            || this.periods[this.currentWidget.config.period] === undefined
            || this.currentWidget.params.minWidth > this.currentWidget.config.width
            || this.currentWidget.params.maxWidth < this.currentWidget.config.width
            || this.currentWidget.params.maxHeight < this.currentWidget.config.height
        ) {
            console.log('Error on widget configuration', this.currentWidget);
            return;
        }

        if (this.sources[this.currentWidget.config.source].needPeriod !== 1) {
            this.currentWidget.config.period = null;
        }

        this.currentWidget.action = 'update';
        this.widgetDisplay();
        this.currentWidgetSet();
    }

    rowDelete(rowId)
    {
        let popup = window.ConfirmPopups.create(
            window.translator.trans('spipu.dashboard.label.confirm_delete_row'),
            window.translator.trans('spipu.dashboard.ui.action.confirm'),
            'trash',
            'danger',
            false,
        );

        popup.addCallbackConfirm(
            function () {
                popup.close();
                this.rowDeleteConfirm(rowId);
            }.bind(this)
        );

        popup.addCallbackCancel(
            function () {
                this.rowActionLeave(rowId, 'delete', 'danger');
            }.bind(this)
        );

        setTimeout(function () {
            this.rowActionEnter(rowId, 'delete', 'danger');
        }.bind(this), 0);
    }

    rowDeleteConfirm(rowId)
    {
        this.widgetFormHide();

        let rowKey = this.getContentRowKey(rowId);

        $('#dashboard-row-' + rowId).remove();
        this.dashboardContent.rows.splice(rowKey, 1);

        if (this.dashboardContent.rows.length < 1) {
            $('#dashboard-rows-empty').show();
        }
        this.dashboardNeedSave('row delete');
    }

    rowClone(rowId)
    {
        let popup = window.ConfirmPopups.create(
            window.translator.trans('spipu.dashboard.label.confirm_clone_row'),
            window.translator.trans('spipu.dashboard.ui.action.confirm'),
            'clone',
            'primary',
            false,
        );

        popup.addCallbackConfirm(
            function () {
                popup.close();
                this.rowCloneConfirm(rowId);
            }.bind(this)
        );

        popup.addCallbackCancel(
            function () {
                this.rowActionLeave(rowId, 'clone', 'primary');
            }.bind(this)
        );

        setTimeout(function () {
            this.rowActionEnter(rowId, 'clone', 'primary');
        }.bind(this), 0);
    }

    rowCloneConfirm(rowId)
    {
        this.rowActionLeave(rowId, 'clone', 'primary');

        let rowKey = this.getContentRowKey(rowId);
        if (rowKey === null) {
            return;
        }

        let currentRow = this.cloneObject(this.dashboardContent.rows[rowKey]);
        if (currentRow.title !== '') {
            currentRow.title += ' - ';
        }
        // Refresh widgets id.
        for (let col of currentRow.cols) {
            for (let widget of col.widgets) {
                widget.id = Math.random().toString(16).slice(2);
            }
        }
        currentRow.title += 'duplicated row';
        this.dashboardContent.rows.push(currentRow);
        this.dashboardDisplayAll();
        this.dashboardNeedSave('row clone');
    }

    rowMoveEventInit()
    {
        this.rowMoveEvent = {
            'action': 'none',
            'dragging': null,
            'draggingRowId': null,
            'draggedOver': null,
            'draggedOverRowId': null
        };
    }

    rowMoveEventGetRow(event)
    {
        let target = $(event.target);
        if (target.hasClass('dashboard-row')) {
            return target;
        }

        return target.closest('div.dashboard-row')
    }

    rowMoveEventDrag(event)
    {
        this.rowMoveEventInit();
        if (this.widgetMoveEvent.dragging) {
            return;
        }

        this.rowMoveEvent.dragging = this.rowMoveEventGetRow(event);
        this.rowMoveEvent.draggingRowId = parseInt(this.rowMoveEvent.dragging.data('row-id'));
    }

    rowMoveEventDragOver(event)
    {
        event.preventDefault();

        if (!this.rowMoveEvent.dragging) {
            return;
        }
        this.rowActionEnterMove(this.rowMoveEvent.draggingRowId);

        let target = this.rowMoveEventGetRow(event);
        let targetRowId = parseInt(target.data('row-id'))

        this.rowMoveEvent.action = 'none';
        this.rowMoveEvent.draggedOver = null;
        $('div.dashboard-row').removeClass('dashboard-row-dragged-over');

        if (this.rowMoveEvent.draggingRowId === targetRowId || this.rowMoveEvent.draggingRowId === targetRowId + 1) {
            return;
        }

        this.rowMoveEvent.action = 'after';
        this.rowMoveEvent.draggedOver = target;
        this.rowMoveEvent.draggedOverRowId = targetRowId;
        this.rowMoveEvent.draggedOver.addClass('dashboard-row-dragged-over');
    }

    rowMoveEventDrop(event)
    {
        event.preventDefault();

        $('div.dashboard-row').removeClass('dashboard-row-dragged-over');

        if (this.rowMoveEvent.dragging) {
            this.rowActionLeaveMove(this.rowMoveEvent.draggingRowId);
            if (this.rowMoveEvent.action !== 'none') {
                let srcRowId = this.rowMoveEvent.draggingRowId;
                let dstRowId = this.rowMoveEvent.draggedOverRowId;

                let srcRowKey = this.getContentRowKey(srcRowId);
                let dstRowKey = this.getContentRowKey(dstRowId);

                if (srcRowKey !== null && (dstRowKey !== null || dstRowId === -1)) {
                    let rows = [];
                    if (dstRowId === -1) {
                        rows.push(this.cloneObject(this.dashboardContent.rows[srcRowKey]));
                    }
                    for (let currentRowKey in this.dashboardContent.rows) {
                        currentRowKey = parseInt(currentRowKey);
                        if (currentRowKey === srcRowKey) {
                            continue;
                        }
                        rows.push(this.cloneObject(this.dashboardContent.rows[currentRowKey]));
                        if (currentRowKey === dstRowKey) {
                            rows.push(this.cloneObject(this.dashboardContent.rows[srcRowKey]));
                        }
                    }
                    this.dashboardContent.rows = rows;
                    this.dashboardDisplayAll();
                    this.dashboardNeedSave('row move');
                }
            }
        }

        this.rowMoveEventInit();
    }

    getContentRowKey(rowId)
    {
        for (let rowK in this.dashboardContent.rows) {
            let row = this.dashboardContent.rows[rowK];
            if (row.id === rowId) {
                return parseInt(rowK);
            }
        }

        return null;
    }

    dashboardSave()
    {
        $.ajax({
            type: "POST",
            url: this.saveRouter,
            data: {
                name: this.dashboardName,
                configurations: JSON.stringify(this.dashboardContent)
            },
            success: function (data) {
                if (data.status === 'ok') {
                    this.dashboardResetSave();
                    this.displayMessageSuccess(window.translator.trans('spipu.dashboard.flash_alert_message.success_saved'));
                }
                if (data.status === 'ko') {
                    this.displayMessageError(data.message);
                }
            }.bind(this),
            error: function () {
                this.displayMessageError('Internal error');
            }.bind(this),
        });
    }

    displayMessageSuccess(message)
    {
        window.AlertInfo.displaySuccess(message);
    }

    displayMessageError(message, autoHide = true)
    {
        window.AlertInfo.displayError(message, autoHide);
    }

    cloneObject(object)
    {
        return JSON.parse(JSON.stringify(object));
    }
}

window.DashboardConfigure = DashboardConfigure;

// spipu-dashboard/dashboard-selector.js

class DashboardSelector {
    constructor(
        pageUrl
    ) {
        this.pageUrl = pageUrl;

        this.init();
    }

    init() {
        let dashboardSelect = $('#dashboard-select');

        dashboardSelect.on('change', function () {
            let id = dashboardSelect.val();
            this.reloadPageWithParam(id);
        }.bind(this));
    }

    reloadPageWithParam(id) {
        window.location.href = this.pageUrl + '/' + id;
    }
}

window.DashboardSelector = DashboardSelector;
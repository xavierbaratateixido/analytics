(function ($) {
    'use strict';

    function parseData($el, attr, fallback) {
        var data = $el.data(attr);
        if (typeof data === 'string') {
            try {
                data = JSON.parse(data);
            } catch (err) {
                data = fallback || [];
            }
        }
        return data || fallback;
    }

    function initDashboardCharts() {
        var downloads = document.getElementById('aad-downloads-chart');
        if (downloads && window.Chart) {
            var labels = parseData($(downloads), 'labels', []);
            var apple = parseData($(downloads), 'apple', []);
            var google = parseData($(downloads), 'google', []);

            new Chart(downloads, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Apple',
                            data: apple,
                            borderColor: '#0071a1',
                            tension: 0.3,
                            fill: false
                        },
                        {
                            label: 'Google',
                            data: google,
                            borderColor: '#34a853',
                            tension: 0.3,
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        var revenue = document.getElementById('aad-revenue-chart');
        if (revenue && window.Chart) {
            var labelsRev = parseData($(revenue), 'labels', []);
            var appleRev = parseData($(revenue), 'apple', []);
            var googleRev = parseData($(revenue), 'google', []);

            new Chart(revenue, {
                type: 'bar',
                data: {
                    labels: labelsRev,
                    datasets: [
                        {
                            label: 'Apple',
                            data: appleRev,
                            backgroundColor: '#0071a1'
                        },
                        {
                            label: 'Google',
                            data: googleRev,
                            backgroundColor: '#34a853'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { stacked: true },
                        y: { stacked: true }
                    }
                }
            });
        }
    }

    function initAppDetailCharts() {
        var downloads = document.getElementById('aad-app-downloads');
        if (downloads && window.Chart) {
            var labels = parseData($(downloads), 'labels', []);
            var dl = parseData($(downloads), 'downloads', []);
            var revenue = parseData($(downloads), 'revenue', []);

            new Chart(downloads, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Downloads',
                            data: dl,
                            borderColor: '#0071a1',
                            tension: 0.3,
                            fill: false
                        },
                        {
                            label: 'Revenue',
                            data: revenue,
                            borderColor: '#fbbc05',
                            tension: 0.3,
                            fill: false,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: { drawOnChartArea: false }
                        }
                    }
                }
            });
        }

        var quality = document.getElementById('aad-app-quality');
        if (quality && window.Chart) {
            var anr = parseFloat(quality.dataset.anr || 0);
            var crashes = parseFloat(quality.dataset.crashes || 0);
            var rating = parseFloat(quality.dataset.rating || 0);

            new Chart(quality, {
                type: 'radar',
                data: {
                    labels: ['ANR', 'Crashes', 'Rating'],
                    datasets: [
                        {
                            label: 'Quality',
                            data: [anr, crashes, rating],
                            backgroundColor: 'rgba(0, 113, 161, 0.2)',
                            borderColor: '#0071a1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
    }

    function initRevenueCharts() {
        $('.revenue-chart').each(function () {
            var canvas = this;
            if (!window.Chart) {
                return;
            }
            var labels = parseData($(canvas), 'labels', []);
            var values = parseData($(canvas), 'values', []);

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Revenue',
                            data: values,
                            backgroundColor: '#fbbc05'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        });
    }

    function initTabs() {
        $('.nav-tab-wrapper .nav-tab').on('click', function (e) {
            e.preventDefault();
            var target = $(this).data('tab');

            $(this).addClass('nav-tab-active').siblings().removeClass('nav-tab-active');
            $('.tab-panel').removeClass('active');
            $('#' + target).addClass('active');
        });
    }

    function initStoreFields() {
        $('#aad-app-store').on('change', function () {
            var store = $(this).val();
            $('.store-field').hide();
            $('.store-field-' + store).show();
        }).trigger('change');
    }

    function initSelectAll() {
        $('.select-all').on('change', function () {
            var checked = $(this).is(':checked');
            $(this).closest('table').find('tbody input[type="checkbox"]').prop('checked', checked);
        });
    }

    $(function () {
        initDashboardCharts();
        initAppDetailCharts();
        initRevenueCharts();
        initTabs();
        initStoreFields();
        initSelectAll();
    });
})(jQuery);

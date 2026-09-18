// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Time Tracker report UI (AMD).
 *
 * @module     local_timetracker/report
 * @author     BitKea Technologies LLP
 * @copyright  2026 BitKea Technologies LLP
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    var ajaxUrl = '';
    var downloadUrl = '';
    var config = {
        sesskey: '',
        nodata: 'No data available in table',
        showingrecords: 'Showing %%FROM%% - %%TO%% of %%TOTAL%%',
        colcount: 5
    };
    var initialized = false;

    /**
     * @param {string} id
     * @return {HTMLElement|null}
     */
    function byId(id) {
        return document.getElementById(id);
    }

    /**
     * @return {string}
     */
    function sesskey() {
        if (config.sesskey) {
            return config.sesskey;
        }
        var el = byId('timetracker-sesskey');
        if (el && el.value) {
            return el.value;
        }
        return '';
    }

    /**
     * @return {number}
     */
    function currentPage() {
        var el = byId('pagenumber');
        return el ? (parseInt(el.value, 10) || 1) : 1;
    }

    /**
     * @param {number} page
     */
    function setPage(page) {
        var el = byId('pagenumber');
        if (el) {
            el.value = String(page);
        }
    }

    /**
     * @return {number}
     */
    function selectedCourseId() {
        var el = byId('courseid');
        return el ? (parseInt(el.value, 10) || 0) : 0;
    }

    /**
     * @param {string} base
     * @param {Object} params
     * @return {string}
     */
    function buildUrl(base, params) {
        var url;
        try {
            url = new URL(base, window.location.href);
        } catch (e) {
            url = document.createElement('a');
            url.href = base;
            var qs = Object.keys(params).map(function(key) {
                return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
            }).join('&');
            return url.href + (url.href.indexOf('?') === -1 ? '?' : '&') + qs;
        }
        Object.keys(params).forEach(function(key) {
            if (params[key] !== undefined && params[key] !== null) {
                url.searchParams.set(key, params[key]);
            }
        });
        return url.toString();
    }

    /**
     * @param {boolean} isLoading
     */
    function setLoading(isLoading) {
        var el = byId('ajaxloading');
        if (!el) {
            return;
        }
        el.style.display = isLoading ? 'block' : 'none';
    }

    /**
     */
    function updateEmptyState() {
        var empty = byId('timetracker-empty');
        if (!empty) {
            return;
        }
        if (selectedCourseId() > 0) {
            empty.classList.add('d-none');
            empty.style.display = 'none';
        } else {
            empty.classList.remove('d-none');
            empty.style.display = '';
        }
    }

    /**
     * @return {string}
     */
    function pagingTemplate() {
        var root = document.querySelector('.local-timetracker-report');
        if (root && root.getAttribute('data-showingrecords')) {
            return root.getAttribute('data-showingrecords');
        }
        return config.showingrecords;
    }

    /**
     * @param {number} from
     * @param {number} to
     * @param {number} total
     * @return {string}
     */
    function formatPagingSummary(from, to, total) {
        return pagingTemplate()
            .replace('%%FROM%%', String(from))
            .replace('%%TO%%', String(to))
            .replace('%%TOTAL%%', String(total));
    }

    /**
     * @param {HTMLElement|null} link
     * @param {boolean} disabled
     */
    function setPagerLinkState(link, disabled) {
        if (!link) {
            return;
        }
        link.setAttribute('aria-disabled', disabled ? 'true' : 'false');
        if (disabled) {
            link.setAttribute('disabled', 'disabled');
            link.setAttribute('tabindex', '-1');
        } else {
            link.removeAttribute('disabled');
            link.removeAttribute('tabindex');
        }
        var item = link.closest('.page-item');
        if (item) {
            item.classList.toggle('disabled', disabled);
        }
    }

    /**
     * @param {HTMLElement|null} link
     * @return {boolean}
     */
    function isPagerDisabled(link) {
        if (!link) {
            return true;
        }
        if (link.getAttribute('aria-disabled') === 'true') {
            return true;
        }
        if (link.hasAttribute('disabled')) {
            return true;
        }
        var item = link.closest('.page-item');
        return item ? item.classList.contains('disabled') : false;
    }

    /**
     * @param {Object} data
     */
    function updatePager(data) {
        var total = parseInt(data.total, 10) || 0;
        var from = parseInt(data.strarfrom, 10) || 0;
        var to = parseInt(data.limitto, 10) || 0;
        var perPageEl = byId('rec_per_page');
        var perPage = perPageEl ? (parseInt(perPageEl.value, 10) || 10) : 10;
        var page = currentPage();
        var maxPage = total > 0 ? Math.ceil(total / perPage) : 1;

        var root = document.querySelector('.local-timetracker-report') || document;
        var summaryEl = root.querySelector('.timetracker-paging-summary');
        if (summaryEl) {
            summaryEl.textContent = total > 0 ? formatPagingSummary(from, to, total) : '';
        }

        setPagerLinkState(byId('timetracker-prev'), page <= 1 || total === 0);
        setPagerLinkState(byId('timetracker-next'), page >= maxPage || total === 0);
    }

    /**
     * @return {string}
     */
    function noDataMessage() {
        var root = document.querySelector('.local-timetracker-report');
        if (root && root.getAttribute('data-nodata')) {
            return root.getAttribute('data-nodata');
        }
        return config.nodata;
    }

    /**
     * @return {number}
     */
    function columnCount() {
        var root = document.querySelector('.local-timetracker-report');
        var count = root ? parseInt(root.getAttribute('data-colcount'), 10) : 0;
        if (count > 0) {
            return count;
        }
        if (config.colcount > 0) {
            return config.colcount;
        }
        var ths = document.querySelectorAll('#timetracker-index-table thead th');
        return ths.length || 5;
    }

    /**
     * @param {Array} reports
     */
    function renderRows(reports) {
        var table = byId('timetracker-index-table');
        if (!table) {
            return;
        }
        var tbody = table.querySelector('tbody');
        if (!tbody) {
            return;
        }
        tbody.innerHTML = '';
        if (!reports || !reports.length) {
            if (selectedCourseId() > 0) {
                var tr = document.createElement('tr');
                var td = document.createElement('td');
                td.colSpan = columnCount();
                td.className = 'timetracker-empty-row text-center text-muted';
                td.textContent = noDataMessage();
                tr.appendChild(td);
                tbody.appendChild(tr);
            }
            return;
        }
        reports.forEach(function(row) {
            var rowEl = document.createElement('tr');
            row.forEach(function(cell) {
                var cellEl = document.createElement('td');
                cellEl.innerHTML = cell;
                rowEl.appendChild(cellEl);
            });
            tbody.appendChild(rowEl);
        });
    }

    /**
     */
    function filtertable() {
        updateEmptyState();
        if (!selectedCourseId()) {
            renderRows([]);
            updatePager({total: 0, strarfrom: 0, limitto: 0});
            setLoading(false);
            return;
        }
        if (!ajaxUrl) {
            return;
        }

        setLoading(true);
        var params = {
            courseid: selectedCourseId(),
            currentpagenumber: currentPage(),
            rec_per_page: byId('rec_per_page').value,
            searchdata: (byId('searchdata') && byId('searchdata').value) || '',
            sesskey: sesskey()
        };

        fetch(buildUrl(ajaxUrl, params), {credentials: 'same-origin'})
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Request failed (' + response.status + ')');
                }
                return response.text();
            })
            .then(function(text) {
                var data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response');
                }
                renderRows(data.reports || []);
                updatePager(data);
                return data;
            })
            .catch(function() {
                renderRows([]);
                updatePager({total: 0, strarfrom: 0, limitto: 0});
            })
            .finally(function() {
                setLoading(false);
            });
    }

    /**
     * @param {string} dataformat
     */
    function download(dataformat) {
        if (!selectedCourseId()) {
            return;
        }
        var url = downloadUrl || (byId('downloadajaxurl') && byId('downloadajaxurl').value) || '';
        if (!url) {
            return;
        }
        window.location.href = buildUrl(url, {
            courseid: selectedCourseId(),
            searchdata: (byId('searchdata') && byId('searchdata').value) || '',
            dataformat: dataformat,
            sesskey: sesskey()
        });
    }

    /**
     * @param {Object} cfg
     */
    function init(cfg) {
        if (initialized) {
            return;
        }
        cfg = cfg || {};
        config.sesskey = cfg.sesskey || config.sesskey;
        config.nodata = cfg.nodata || config.nodata;
        config.showingrecords = cfg.showingrecords || config.showingrecords;
        config.colcount = parseInt(cfg.colcount, 10) || config.colcount;
        ajaxUrl = cfg.ajaxurl || '';
        downloadUrl = cfg.downloadurl || '';

        var root = document.querySelector('.local-timetracker-report');
        var courseSelect = byId('courseid');
        var ajaxInput = byId('ajaxUrl');
        if (!root || !courseSelect) {
            return;
        }
        if (!ajaxUrl && ajaxInput) {
            ajaxUrl = ajaxInput.value;
        }
        if (!downloadUrl && byId('downloadajaxurl')) {
            downloadUrl = byId('downloadajaxurl').value;
        }
        initialized = true;

        setLoading(false);
        updateEmptyState();
        updatePager({total: 0, strarfrom: 0, limitto: 0});

        courseSelect.addEventListener('change', function() {
            setPage(1);
            filtertable();
        });

        var searchBtn = byId('btnsearch');
        if (searchBtn) {
            searchBtn.addEventListener('click', function() {
                setPage(1);
                filtertable();
            });
        }

        var searchInput = byId('searchdata');
        if (searchInput) {
            searchInput.addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    setPage(1);
                    filtertable();
                }
            });
        }

        var perPage = byId('rec_per_page');
        if (perPage) {
            perPage.addEventListener('change', function() {
                setPage(1);
                filtertable();
            });
        }

        var prev = byId('timetracker-prev');
        if (prev) {
            prev.addEventListener('click', function(event) {
                event.preventDefault();
                if (isPagerDisabled(prev)) {
                    return;
                }
                setPage(Math.max(1, currentPage() - 1));
                filtertable();
            });
        }

        var next = byId('timetracker-next');
        if (next) {
            next.addEventListener('click', function(event) {
                event.preventDefault();
                if (isPagerDisabled(next)) {
                    return;
                }
                setPage(currentPage() + 1);
                filtertable();
            });
        }

        document.querySelectorAll('#dataexport [data-export]').forEach(function(item) {
            item.addEventListener('click', function() {
                download(item.getAttribute('data-export'));
            });
        });
    }

    return {
        init: init
    };
});

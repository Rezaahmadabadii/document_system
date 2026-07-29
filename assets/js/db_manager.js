// ===== مدیریت دیتابیس =====

// ========== بارگذاری آخرین تاریخ تحویل کاربر ==========
function loadUserLastDate(context, userId, specificDate = null) {
    if (!userId) {
        document.getElementById(context + '_last_date_group').style.display = 'none';
        return;
    }
    
    let dateSpanId = context + '_last_date';
    let groupId = context + '_last_date_group';
    let linkId = context + '_last_date_link';
    let counterId = context + '_date_counter';
    
    if (context === 'change_delivery') {
        dateSpanId = 'change_delivery_current_date';
        linkId = 'change_delivery_current_date_link';
    }
    
    const dateSpan = document.getElementById(dateSpanId);
    
    let deliveryDate = specificDate;
    if (!deliveryDate && dateSpan) {
        deliveryDate = dateSpan.textContent;
    }
    
    let url = 'api/ajax.php?action=get_user_last_delivery_date&user_id=' + userId;
    if (deliveryDate && deliveryDate !== '-' && deliveryDate !== '') {
        url += '&delivery_date=' + encodeURIComponent(deliveryDate);
    }
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.last_date) {
                const group = document.getElementById(groupId);
                const link = document.getElementById(linkId);
                const counterSpan = document.getElementById(counterId);
                
                if (dateSpan) dateSpan.textContent = data.last_date;
                if (group) group.style.display = 'block';
                
                fetch('api/ajax.php?action=get_user_all_delivery_dates&user_id=' + userId)
                    .then(res => res.json())
                    .then(datesData => {
                        if (datesData.success && datesData.dates && datesData.dates.length > 0) {
                            const allDates = datesData.dates;
                            const currentIndex = allDates.indexOf(data.last_date);
                            if (counterSpan && currentIndex !== -1) {
                                counterSpan.textContent = '(تاریخ ' + (currentIndex + 1) + ' از ' + allDates.length + ')';
                            } else if (counterSpan) {
                                counterSpan.textContent = '(تاریخ 1 از ' + allDates.length + ')';
                            }
                        }
                    })
                    .catch(err => console.error(err));
                
                if (link) {
                    link.href = '#';
                    link.onclick = function(e) {
                        e.preventDefault();
                        goToDeliveryDate(context, e);
                    };
                }
                
                if (context === 'revert_user') {
                    const statusSpan = document.getElementById('revert_user_signature_status');
                    if (statusSpan) {
                        statusSpan.textContent = data.user_signed ? '✅ امضا شده' : '⏳ بدون امضا';
                        statusSpan.style.color = data.user_signed ? '#10b981' : '#f59e0b';
                    }
                } else if (context === 'revert_admin') {
                    const statusSpan = document.getElementById('revert_admin_signature_status');
                    if (statusSpan) {
                        statusSpan.textContent = data.admin_approved ? '✅ تایید شده' : '⏳ بدون تایید';
                        statusSpan.style.color = data.admin_approved ? '#10b981' : '#f59e0b';
                    }
                } else if (context === 'change_delivery') {
                    const countSpan = document.getElementById('change_delivery_doc_count');
                    if (countSpan) countSpan.textContent = data.doc_count || 0;
                }
            } else {
                document.getElementById(context + '_last_date_group').style.display = 'none';
            }
        })
        .catch(err => console.error(err));
}

// ========== تغییر تاریخ تحویل با دکمه‌های + و - ==========
function changeDeliveryDate(context, direction) {
    const userIdSelect = document.getElementById(context + '_user_id');
    const userId = userIdSelect ? userIdSelect.value : null;
    
    if (!userId) {
        showToast('لطفاً ابتدا کاربر را انتخاب کنید', true);
        return;
    }
    
    let dateSpanId = context + '_last_date';
    let counterId = context + '_date_counter';
    
    if (context === 'change_delivery') {
        dateSpanId = 'change_delivery_current_date';
        counterId = 'change_delivery_date_counter';
    }
    
    const dateSpan = document.getElementById(dateSpanId);
    if (!dateSpan || !dateSpan.textContent || dateSpan.textContent === '-') {
        showToast('تاریخ تحویلی برای این کاربر وجود ندارد', true);
        return;
    }
    
    const currentDate = dateSpan.textContent;
    
    fetch('api/ajax.php?action=get_user_all_delivery_dates&user_id=' + userId)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.dates && data.dates.length > 0) {
                const dates = data.dates;
                const currentIndex = dates.indexOf(currentDate);
                
                if (currentIndex === -1) {
                    showToast('تاریخ فعلی در لیست یافت نشد', true);
                    return;
                }
                
                let newIndex = currentIndex + direction;
                
                if (newIndex < 0) newIndex = 0;
                if (newIndex >= dates.length) newIndex = dates.length - 1;
                
                if (newIndex === currentIndex) {
                    if (direction === -1) {
                        showToast('این اولین تاریخ است', false);
                    } else {
                        showToast('این آخرین تاریخ است', false);
                    }
                    return;
                }
                
                const newDate = dates[newIndex];
                
                dateSpan.textContent = newDate;
                
                const counterSpan = document.getElementById(counterId);
                if (counterSpan) {
                    counterSpan.textContent = '(تاریخ ' + (newIndex + 1) + ' از ' + dates.length + ')';
                }
                
                loadUserLastDate(context, userId, newDate);
                
            } else {
                showToast('هیچ تاریخ دیگری برای این کاربر یافت نشد', true);
            }
        })
        .catch(err => console.error(err));
}

// ========== نمایش Toast ==========
function showToast(message, isError = false) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    
    toast.textContent = message;
    toast.style.background = isError ? '#ef4444' : '#10b981';
    toast.style.color = 'white';
    toast.style.display = 'block';
    toast.style.padding = '10px 20px';
    toast.style.borderRadius = '8px';
    toast.style.position = 'fixed';
    toast.style.bottom = '20px';
    toast.style.right = '20px';
    toast.style.zIndex = '9999';
    toast.style.fontSize = '0.8rem';
    
    clearTimeout(toast._timeout);
    toast._timeout = setTimeout(function() {
        toast.style.display = 'none';
    }, 3000);
}

// ========== رفتن به تاریخ تحویل انتخاب شده ==========
function goToDeliveryDate(context, event) {
    if (event) event.preventDefault();
    
    let dateSpanId = context + '_last_date';
    let userIdInputId = context + '_user_id';
    
    if (context === 'change_delivery') {
        dateSpanId = 'change_delivery_current_date';
        userIdInputId = 'change_delivery_user_id';
    }
    
    const dateSpan = document.getElementById(dateSpanId);
    const userIdSelect = document.getElementById(userIdInputId);
    
    if (!dateSpan || !dateSpan.textContent || dateSpan.textContent === '-' || dateSpan.textContent === '') {
        showToast('تاریخ تحویلی برای این کاربر وجود ندارد', true);
        return;
    }
    
    const deliveryDate = dateSpan.textContent;
    const userId = userIdSelect ? userIdSelect.value : null;
    
    closeDbManagerModal();
    
    let url = 'index.php?delivery_date=' + encodeURIComponent(deliveryDate);
    if (userId) {
        url += '&user_id=' + userId;
    }
    
    window.location.href = url;
}

// ========== بارگذاری شرکت سند بر اساس شماره سند ==========
function loadDocumentCompany(docNumber) {
    const userId = document.getElementById('change_company_user_id').value;
    const deliveryDate = document.getElementById('change_company_last_date').textContent;
    const currentNameSpan = document.getElementById('change_company_current_name');
    const docIdInput = document.getElementById('change_company_doc_id');
    
    if (!userId || !deliveryDate || deliveryDate === '-' || !docNumber) {
        if (currentNameSpan) currentNameSpan.textContent = '-';
        if (docIdInput) docIdInput.value = '';
        return;
    }
    
    fetch('api/ajax.php?action=get_document_by_number&user_id=' + userId + '&delivery_date=' + encodeURIComponent(deliveryDate) + '&doc_number=' + encodeURIComponent(docNumber))
        .then(res => res.json())
        .then(data => {
            if (data.success && data.document) {
                currentNameSpan.textContent = data.document.company_name;
                docIdInput.value = data.document.id;
                if (document.getElementById('change_company_new_company').options.length <= 1) {
                    loadCompaniesList('change_company_new_company');
                }
            } else {
                currentNameSpan.textContent = '❌ سند یافت نشد';
                docIdInput.value = '';
            }
        })
        .catch(err => console.error(err));
}

// ========== بارگذاری لیست شرکت‌ها ==========
function loadCompaniesList(selectId) {
    const select = document.getElementById(selectId);
    if (!select) return;
    
    fetch('api/ajax.php?action=get_companies')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.companies) {
                select.innerHTML = '<option value="">انتخاب کنید...</option>';
                data.companies.forEach(function(company) {
                    const option = document.createElement('option');
                    option.value = company.id;
                    option.textContent = company.name;
                    select.appendChild(option);
                });
            }
        })
        .catch(err => console.error(err));
}

// ========== برگشت امضای کاربر ==========
function revertUserSignature() {
    const userId = document.getElementById('revert_user_user_id').value;
    const deliveryDate = document.getElementById('revert_user_last_date').textContent;
    const status = document.getElementById('revert_user_status');
    
    if (!userId) {
        showStatus(status, 'لطفاً کاربر را انتخاب کنید', 'error');
        return;
    }
    if (!deliveryDate || deliveryDate === '-') {
        showStatus(status, 'کاربر تاریخ تحویلی ندارد', 'error');
        return;
    }
    
    const statusSpan = document.getElementById('revert_user_signature_status');
    if (statusSpan && statusSpan.textContent.includes('بدون امضا')) {
        showStatus(status, '⚠️ این تاریخ امضا نشده است', 'error');
        return;
    }
    
    if (!confirm('آیا از برگشت امضای کاربر برای تاریخ ' + deliveryDate + ' اطمینان دارید؟')) return;
    
    const btn = document.querySelector('#revertUserCard .btn-manager');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch('api/ajax.php?action=revert_user_signature_by_date', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId, delivery_date: deliveryDate })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showStatus(status, '✅ امضای کاربر برگشت داده شد', 'success');
            loadUserLastDate('revert_user', userId);
        } else {
            showStatus(status, '❌ ' + (data.error || 'خطا'), 'error');
        }
    })
    .catch(err => {
        showStatus(status, '❌ خطا در ارتباط با سرور', 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

// ========== برگشت امضای ادمین ==========
function revertAdminSignature() {
    const userId = document.getElementById('revert_admin_user_id').value;
    const deliveryDate = document.getElementById('revert_admin_last_date').textContent;
    const status = document.getElementById('revert_admin_status');
    
    if (!userId) {
        showStatus(status, 'لطفاً کاربر را انتخاب کنید', 'error');
        return;
    }
    if (!deliveryDate || deliveryDate === '-') {
        showStatus(status, 'کاربر تاریخ تحویلی ندارد', 'error');
        return;
    }
    
    const statusSpan = document.getElementById('revert_admin_signature_status');
    if (statusSpan && statusSpan.textContent.includes('بدون تایید')) {
        showStatus(status, '⚠️ این تاریخ تایید نشده است', 'error');
        return;
    }
    
    if (!confirm('آیا از برگشت تایید ادمین برای تاریخ ' + deliveryDate + ' اطمینان دارید؟')) return;
    
    const btn = document.querySelector('#revertAdminCard .btn-manager');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch('api/ajax.php?action=revert_admin_signature_by_date', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId, delivery_date: deliveryDate })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showStatus(status, '✅ تایید ادمین برگشت داده شد', 'success');
            loadUserLastDate('revert_admin', userId);
        } else {
            showStatus(status, '❌ ' + (data.error || 'خطا'), 'error');
        }
    })
    .catch(err => {
        showStatus(status, '❌ خطا در ارتباط با سرور', 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

// ========== تغییر نام شرکت ==========
function changeCompanyName() {
    const userId = document.getElementById('change_company_user_id').value;
    const docId = document.getElementById('change_company_doc_id').value;
    const newCompanyId = document.getElementById('change_company_new_company').value;
    const status = document.getElementById('change_company_status');
    
    if (!userId) {
        showStatus(status, 'لطفاً کاربر را انتخاب کنید', 'error');
        return;
    }
    if (!docId) {
        showStatus(status, 'سند مورد نظر یافت نشد', 'error');
        return;
    }
    if (!newCompanyId) {
        showStatus(status, 'لطفاً شرکت جدید را انتخاب کنید', 'error');
        return;
    }
    
    if (!confirm('آیا نام شرکت این سند تغییر یابد؟')) return;
    
    const btn = document.querySelector('#changeCompanyCard .btn-manager');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch('api/ajax.php?action=change_document_company', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ doc_id: docId, company_id: newCompanyId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showStatus(status, '✅ نام شرکت تغییر یافت', 'success');
            document.getElementById('change_company_doc_number').value = '';
            document.getElementById('change_company_current_name').textContent = '-';
            document.getElementById('change_company_doc_id').value = '';
        } else {
            showStatus(status, '❌ ' + (data.error || 'خطا'), 'error');
        }
    })
    .catch(err => {
        showStatus(status, '❌ خطا در ارتباط با سرور', 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

// ========== تغییر تاریخ تحویل کاربر ==========
function changeUserDeliveryDate() {
    const userId = document.getElementById('change_delivery_user_id').value;
    const currentDate = document.getElementById('change_delivery_current_date').textContent;
    const newDate = document.getElementById('change_delivery_new_date').value.trim();
    const status = document.getElementById('change_delivery_status');
    
    if (!userId) {
        showStatus(status, 'لطفاً کاربر را انتخاب کنید', 'error');
        return;
    }
    if (!currentDate || currentDate === '-') {
        showStatus(status, 'کاربر تاریخ تحویلی ندارد', 'error');
        return;
    }
    if (!newDate) {
        showStatus(status, 'لطفاً تاریخ جدید را وارد کنید', 'error');
        return;
    }
    
    if (!confirm('تاریخ تحویل کاربر از "' + currentDate + '" به "' + newDate + '" تغییر یابد؟')) return;
    
    const btn = document.querySelector('#changeDeliveryDateCard .btn-manager');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch('api/ajax.php?action=change_user_delivery_date', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            user_id: userId, 
            old_date: currentDate, 
            new_date: newDate 
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showStatus(status, '✅ تاریخ تحویل تغییر یافت (' + data.doc_count + ' سند)', 'success');
            document.getElementById('change_delivery_new_date').value = '';
            loadUserLastDate('change_delivery', userId);
        } else {
            showStatus(status, '❌ ' + (data.error || 'خطا'), 'error');
        }
    })
    .catch(err => {
        showStatus(status, '❌ خطا در ارتباط با سرور', 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

// ========== نمایش وضعیت ==========
function showStatus(element, message, type) {
    if (!element) return;
    element.textContent = message;
    element.className = 'db-manager-status ' + type;
    element.style.display = 'block';
    
    if (element._timeout) {
        clearTimeout(element._timeout);
    }
    
    element._timeout = setTimeout(function() {
        element.style.display = 'none';
    }, 5000);
}

// ========== بارگذاری داده‌های مدیریت دیتابیس ==========
function loadDbManagerData() {
    fetch('api/ajax.php?action=get_users')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.users) {
                populateSelect('revert_user_user_id', data.users, 'fullname');
                populateSelect('revert_admin_user_id', data.users, 'fullname');
                populateSelect('change_company_user_id', data.users, 'fullname');
                populateSelect('change_delivery_user_id', data.users, 'fullname');
            }
        })
        .catch(err => console.error('خطا در بارگذاری کاربران:', err));
    
    fetch('api/ajax.php?action=get_companies')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.companies) {
                const select = document.getElementById('change_company_new_company');
                if (select) {
                    select.innerHTML = '<option value="">انتخاب کنید...</option>';
                    data.companies.forEach(function(company) {
                        const option = document.createElement('option');
                        option.value = company.id;
                        option.textContent = company.name;
                        select.appendChild(option);
                    });
                }
            }
        })
        .catch(err => console.error('خطا در بارگذاری شرکت‌ها:', err));
}

// ========== پر کردن سلکت ==========
function populateSelect(id, items, labelKey) {
    var select = document.getElementById(id);
    if (!select) return;
    
    var currentValue = select.value;
    
    select.innerHTML = '<option value="">انتخاب کنید...</option>';
    items.forEach(function(item) {
        var option = document.createElement('option');
        option.value = item.id;
        var label = item[labelKey] || item.name || '';
        option.textContent = label;
        select.appendChild(option);
    });
    
    if (currentValue) {
        var exists = false;
        for (var i = 0; i < select.options.length; i++) {
            if (select.options[i].value == currentValue) {
                exists = true;
                break;
            }
        }
        if (exists) {
            select.value = currentValue;
        }
    }
}

// ========== نمایش مودال مدیریت دیتابیس ==========
function showDbManagerModal() {
    // همیشه مودال رمز را نمایش بده
    showDbPasswordModal();
}

// ========== نمایش مودال رمز ==========
function showDbPasswordModal() {
    var modal = document.getElementById('dbPasswordModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('active');
        var input = document.getElementById('dbPasswordInput');
        if (input) {
            input.value = '';
            input.focus();
        }
        var error = document.getElementById('dbPasswordError');
        if (error) error.textContent = '';
    }
}

// ========== تایید رمز ==========
function confirmDbPassword() {
    var password = document.getElementById('dbPasswordInput').value;
    var errorEl = document.getElementById('dbPasswordError');
    
    if (!password) {
        errorEl.textContent = '❌ لطفاً رمز عبور را وارد کنید';
        errorEl.style.color = '#ef4444';
        return;
    }
    
    var btn = document.getElementById('dbPasswordBtn');
    var originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch('api/ajax.php?action=check_db_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ password: password })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeDbPasswordModal();
            openDbManagerModal();
        } else {
            errorEl.textContent = '❌ ' + (data.error || 'رمز عبور اشتباه است');
            errorEl.style.color = '#ef4444';
            document.getElementById('dbPasswordInput').value = '';
            document.getElementById('dbPasswordInput').focus();
        }
    })
    .catch(err => {
        errorEl.textContent = '❌ خطا در ارتباط با سرور';
        errorEl.style.color = '#ef4444';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

// ========== بستن مودال رمز ==========
function closeDbPasswordModal() {
    var modal = document.getElementById('dbPasswordModal');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
    }
}

// ========== باز کردن مودال مدیریت دیتابیس ==========
function openDbManagerModal() {
    var modal = document.getElementById('dbManagerModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        if (typeof loadDbManagerData === 'function') {
            loadDbManagerData();
        }
        document.querySelectorAll('.db-tab').forEach(function(tab) {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.db-tab-panel').forEach(function(panel) {
            panel.classList.remove('active');
        });
        var firstTab = document.querySelector('.db-tab');
        if (firstTab) firstTab.classList.add('active');
        var firstPanel = document.getElementById('dbTab_revert');
        if (firstPanel) firstPanel.classList.add('active');
    }
}

// ========== بستن مودال مدیریت دیتابیس ==========
function closeDbManagerModal() {
    var modal = document.getElementById('dbManagerModal');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
        document.body.style.overflow = '';
        
        // ✅ پاک کردن دسترسی سشن
        fetch('api/ajax.php?action=clear_db_access', { method: 'POST' })
            .catch(err => console.error(err));
    }
}

// ========== تغییر تب ==========
function switchDbTab(tabName) {
    document.querySelectorAll('.db-tab').forEach(function(tab) {
        tab.classList.remove('active');
        if (tab.getAttribute('data-tab') === tabName) {
            tab.classList.add('active');
        }
    });
    
    document.querySelectorAll('.db-tab-panel').forEach(function(panel) {
        panel.classList.remove('active');
    });
    
    var targetPanel = document.getElementById('dbTab_' + tabName);
    if (targetPanel) {
        targetPanel.classList.add('active');
    }
}

// ========== رویدادهای مودال ==========
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('dbManagerModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeDbManagerModal();
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeDbManagerModal();
            }
        });
    }
});

// ========== تابع سازگاری با نسخه قبلی ==========
function showDbManager() {
    showDbManagerModal();
}

// ===================================================================
// ========== توابع جدید برای تغییر اطلاعات سند ==========
// ===================================================================

// ========== بارگذاری سند برای ویرایش ==========
function loadDocumentForEdit(docNumber) {
    const userId = document.getElementById('change_company_user_id').value;
    const deliveryDate = document.getElementById('change_company_last_date').textContent;
    const statusSpan = document.getElementById('change_company_status');
    const infoDiv = document.getElementById('change_company_info');
    
    if (!userId || !deliveryDate || deliveryDate === '-' || !docNumber) {
        if (infoDiv) infoDiv.style.display = 'none';
        if (statusSpan) {
            statusSpan.textContent = 'لطفاً شماره سند را وارد کنید';
            statusSpan.style.color = '#94a3b8';
        }
        return;
    }
    
    fetch('api/ajax.php?action=get_document_full_info&user_id=' + userId + '&delivery_date=' + encodeURIComponent(deliveryDate) + '&doc_number=' + encodeURIComponent(docNumber))
        .then(res => res.json())
        .then(data => {
            if (data.success && data.document) {
                document.getElementById('change_company_current_number').textContent = data.document.doc_number || '-';
                document.getElementById('change_company_current_date').textContent = data.document.doc_date || '-';
                document.getElementById('change_company_current_company').textContent = data.document.company_name || '-';
                document.getElementById('change_company_doc_id').value = data.document.id;
                
                document.getElementById('change_company_new_number').value = data.document.doc_number || '';
                document.getElementById('change_company_new_date').value = (data.document.doc_date && data.document.doc_date !== '-') ? data.document.doc_date : '';
                
                const companySelect = document.getElementById('change_company_new_company');
                if (data.document.company_id) {
                    companySelect.value = data.document.company_id;
                }
                
                infoDiv.style.display = 'block';
                statusSpan.textContent = '✅ سند یافت شد';
                statusSpan.style.color = '#10b981';
                
                if (companySelect.options.length <= 1) {
                    loadCompaniesList('change_company_new_company');
                }
            } else {
                infoDiv.style.display = 'none';
                document.getElementById('change_company_doc_id').value = '';
                statusSpan.textContent = '❌ سند یافت نشد';
                statusSpan.style.color = '#ef4444';
            }
        })
        .catch(err => {
            console.error(err);
            statusSpan.textContent = '❌ خطا در ارتباط با سرور';
            statusSpan.style.color = '#ef4444';
        });
}

// ========== ذخیره تغییرات سند ==========
function updateDocumentInfo() {
    const docId = document.getElementById('change_company_doc_id').value;
    const newNumber = document.getElementById('change_company_new_number').value.trim();
    const newDate = document.getElementById('change_company_new_date').value.trim();
    const newCompanyId = document.getElementById('change_company_new_company').value;
    const status = document.getElementById('change_company_status_msg');
    
    if (!docId) {
        showStatus(status, 'لطفاً ابتدا یک سند را جستجو کنید', 'error');
        return;
    }
    if (!newNumber) {
        showStatus(status, 'لطفاً شماره سند جدید را وارد کنید', 'error');
        return;
    }
    if (!newCompanyId) {
        showStatus(status, 'لطفاً شرکت جدید را انتخاب کنید', 'error');
        return;
    }
    
    if (!confirm('آیا از تغییر اطلاعات این سند اطمینان دارید؟')) return;
    
    const btn = document.querySelector('#changeCompanyCard .btn-manager');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch('api/ajax.php?action=update_document_info', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            doc_id: docId, 
            doc_number: newNumber, 
            doc_date: newDate || '-',
            company_id: newCompanyId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showStatus(status, '✅ اطلاعات سند با موفقیت تغییر یافت', 'success');
            document.getElementById('change_company_doc_number').value = '';
            document.getElementById('change_company_new_number').value = '';
            document.getElementById('change_company_new_date').value = '';
            document.getElementById('change_company_info').style.display = 'none';
            document.getElementById('change_company_doc_id').value = '';
            document.getElementById('change_company_status').textContent = 'برای جستجو شماره سند را وارد کنید';
            document.getElementById('change_company_status').style.color = '#94a3b8';
        } else {
            showStatus(status, '❌ ' + (data.error || 'خطا'), 'error');
        }
    })
    .catch(err => {
        showStatus(status, '❌ خطا در ارتباط با سرور', 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}
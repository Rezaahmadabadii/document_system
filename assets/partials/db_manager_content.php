<!-- ===== محتوای مودال مدیریت دیتابیس ===== -->
<div class="db-manager-modal-content">
    <!-- تب‌ها -->
    <div class="db-manager-tabs">
        <button class="db-tab active" data-tab="revert" onclick="switchDbTab('revert')">
            <i class="fas fa-undo-alt"></i> برگشت امضا
        </button>
        <button class="db-tab" data-tab="company" onclick="switchDbTab('company')">
            <i class="fas fa-edit"></i> تغییر اطلاعات سند
        </button>
        <button class="db-tab" data-tab="delivery" onclick="switchDbTab('delivery')">
            <i class="fas fa-calendar"></i> تغییر تاریخ تحویل
        </button>
    </div>
    
    <div class="db-tab-content">
        <!-- ===== تب 1: برگشت امضا ===== -->
        <div id="dbTab_revert" class="db-tab-panel active">
            <div class="db-manager-grid">
                <!-- کارت برگشت امضای کاربر -->
                <div class="db-manager-card" id="revertUserCard">
                    <div class="card-header">
                        <i class="fas fa-user-slash"></i>
                        <span class="card-title">برگشت امضای کاربر</span>
                    </div>
                    <div class="card-desc">انتخاب کاربر و برگشت امضای آخرین تاریخ تحویل</div>
                    
                    <div class="form-group">
                        <label>انتخاب کاربر</label>
                        <select id="revert_user_user_id" onchange="loadUserLastDate('revert_user', this.value)">
                            <option value="">در حال بارگذاری...</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="revert_user_last_date_group" style="display:none;">
                        <label>آخرین تاریخ تحویل</label>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <button type="button" onclick="changeDeliveryDate('revert_user', -1)" style="width:32px; height:32px; border-radius:8px; border:1.5px solid #e2e8f0; background:#f8fafc; cursor:pointer; font-size:1.2rem; font-weight:bold; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:all 0.2s;" onmouseover="this.style.background='#667eea'; this.style.color='white'; this.style.borderColor='#667eea';" onmouseout="this.style.background='#f8fafc'; this.style.color='#475569'; this.style.borderColor='#e2e8f0';">
                                <i class="fas fa-chevron-right" style="font-size:0.7rem;"></i>
                            </button>
                            <a href="#" id="revert_user_last_date_link" onclick="goToDeliveryDate('revert_user', event)" style="flex:1; padding:8px 12px; background:#f1f5f9; border-radius:8px; font-weight:600; text-decoration:none; color:#1e293b; border:1px solid #e2e8f0; transition:all 0.2s; cursor:pointer; text-align:center;" onmouseover="this.style.background='#e2e8f0'; this.style.borderColor='#667eea';" onmouseout="this.style.background='#f1f5f9'; this.style.borderColor='#e2e8f0';">
                                <span id="revert_user_last_date">-</span>
                            </a>
                            <button type="button" onclick="changeDeliveryDate('revert_user', 1)" style="width:32px; height:32px; border-radius:8px; border:1.5px solid #e2e8f0; background:#f8fafc; cursor:pointer; font-size:1.2rem; font-weight:bold; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:all 0.2s;" onmouseover="this.style.background='#667eea'; this.style.color='white'; this.style.borderColor='#667eea';" onmouseout="this.style.background='#f8fafc'; this.style.color='#475569'; this.style.borderColor='#e2e8f0';">
                                <i class="fas fa-chevron-left" style="font-size:0.7rem;"></i>
                            </button>
                        </div>
                        <div style="font-size:0.55rem; color:#94a3b8; margin-top:4px;">
                            وضعیت امضا: <span id="revert_user_signature_status">-</span>
                            <span style="margin-right:10px;" id="revert_user_date_counter">(تاریخ 1 از 1)</span>
                        </div>
                    </div>
                    
                    <button class="btn-manager danger" onclick="revertUserSignature()">برگشت امضا</button>
                    <div class="db-manager-status" id="revert_user_status"></div>
                </div>
                
                <!-- کارت برگشت امضای ادمین -->
                <div class="db-manager-card" id="revertAdminCard">
                    <div class="card-header">
                        <i class="fas fa-user-check"></i>
                        <span class="card-title">برگشت امضای ادمین</span>
                    </div>
                    <div class="card-desc">انتخاب کاربر و برگشت تایید ادمین برای آخرین تاریخ تحویل</div>
                    
                    <div class="form-group">
                        <label>انتخاب کاربر</label>
                        <select id="revert_admin_user_id" onchange="loadUserLastDate('revert_admin', this.value)">
                            <option value="">در حال بارگذاری...</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="revert_admin_last_date_group" style="display:none;">
                        <label>آخرین تاریخ تحویل</label>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <button type="button" onclick="changeDeliveryDate('revert_admin', -1)" style="width:32px; height:32px; border-radius:8px; border:1.5px solid #e2e8f0; background:#f8fafc; cursor:pointer; font-size:1.2rem; font-weight:bold; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:all 0.2s;" onmouseover="this.style.background='#667eea'; this.style.color='white'; this.style.borderColor='#667eea';" onmouseout="this.style.background='#f8fafc'; this.style.color='#475569'; this.style.borderColor='#e2e8f0';">
                                <i class="fas fa-chevron-right" style="font-size:0.7rem;"></i>
                            </button>
                            <a href="#" id="revert_admin_last_date_link" onclick="goToDeliveryDate('revert_admin', event)" style="flex:1; padding:8px 12px; background:#f1f5f9; border-radius:8px; font-weight:600; text-decoration:none; color:#1e293b; border:1px solid #e2e8f0; transition:all 0.2s; cursor:pointer; text-align:center;" onmouseover="this.style.background='#e2e8f0'; this.style.borderColor='#667eea';" onmouseout="this.style.background='#f1f5f9'; this.style.borderColor='#e2e8f0';">
                                <span id="revert_admin_last_date">-</span>
                            </a>
                            <button type="button" onclick="changeDeliveryDate('revert_admin', 1)" style="width:32px; height:32px; border-radius:8px; border:1.5px solid #e2e8f0; background:#f8fafc; cursor:pointer; font-size:1.2rem; font-weight:bold; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:all 0.2s;" onmouseover="this.style.background='#667eea'; this.style.color='white'; this.style.borderColor='#667eea';" onmouseout="this.style.background='#f8fafc'; this.style.color='#475569'; this.style.borderColor='#e2e8f0';">
                                <i class="fas fa-chevron-left" style="font-size:0.7rem;"></i>
                            </button>
                        </div>
                        <div style="font-size:0.55rem; color:#94a3b8; margin-top:4px;">
                            وضعیت تایید: <span id="revert_admin_signature_status">-</span>
                            <span style="margin-right:10px;" id="revert_admin_date_counter">(تاریخ 1 از 1)</span>
                        </div>
                    </div>
                    
                    <button class="btn-manager danger" onclick="revertAdminSignature()">برگشت تایید</button>
                    <div class="db-manager-status" id="revert_admin_status"></div>
                </div>
            </div>
        </div>
        
        <!-- ===== تب 2: تغییر اطلاعات سند ===== -->
        <div id="dbTab_company" class="db-tab-panel">
            <div class="db-manager-grid">
                <div class="db-manager-card" id="changeCompanyCard">
                    <div class="card-header">
                        <i class="fas fa-edit"></i>
                        <span class="card-title">تغییر اطلاعات سند</span>
                    </div>
                    <div class="card-desc">تغییر شماره سند، تاریخ سند و شرکت یک سند خاص</div>
                    
                    <div class="form-group">
                        <label>انتخاب کاربر</label>
                        <select id="change_company_user_id" onchange="loadUserLastDate('change_company', this.value)">
                            <option value="">در حال بارگذاری...</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="change_company_last_date_group" style="display:none;">
                        <label>آخرین تاریخ تحویل</label>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <button type="button" onclick="changeDeliveryDate('change_company', -1)" style="width:32px; height:32px; border-radius:8px; border:1.5px solid #e2e8f0; background:#f8fafc; cursor:pointer; font-size:1.2rem; font-weight:bold; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:all 0.2s;" onmouseover="this.style.background='#667eea'; this.style.color='white'; this.style.borderColor='#667eea';" onmouseout="this.style.background='#f8fafc'; this.style.color='#475569'; this.style.borderColor='#e2e8f0';">
                                <i class="fas fa-chevron-right" style="font-size:0.7rem;"></i>
                            </button>
                            <a href="#" id="change_company_last_date_link" onclick="goToDeliveryDate('change_company', event)" style="flex:1; padding:8px 12px; background:#f1f5f9; border-radius:8px; font-weight:600; text-decoration:none; color:#1e293b; border:1px solid #e2e8f0; transition:all 0.2s; cursor:pointer; text-align:center;" onmouseover="this.style.background='#e2e8f0'; this.style.borderColor='#667eea';" onmouseout="this.style.background='#f1f5f9'; this.style.borderColor='#e2e8f0';">
                                <span id="change_company_last_date">-</span>
                            </a>
                            <button type="button" onclick="changeDeliveryDate('change_company', 1)" style="width:32px; height:32px; border-radius:8px; border:1.5px solid #e2e8f0; background:#f8fafc; cursor:pointer; font-size:1.2rem; font-weight:bold; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:all 0.2s;" onmouseover="this.style.background='#667eea'; this.style.color='white'; this.style.borderColor='#667eea';" onmouseout="this.style.background='#f8fafc'; this.style.color='#475569'; this.style.borderColor='#e2e8f0';">
                                <i class="fas fa-chevron-left" style="font-size:0.7rem;"></i>
                            </button>
                        </div>
                        <div style="font-size:0.55rem; color:#94a3b8; margin-top:4px;">
                            <span id="change_company_date_counter">(تاریخ 1 از 1)</span>
                        </div>
                    </div>
                    
                    <!-- جستجوی سند -->
                    <div class="form-group">
                        <label>شماره سند فعلی</label>
                        <input type="text" id="change_company_doc_number" placeholder="INV-12345" oninput="loadDocumentForEdit(this.value)">
                        <div style="font-size:0.55rem; color:#94a3b8; margin-top:4px;">
                            <span id="change_company_status">برای جستجو شماره سند را وارد کنید</span>
                            <input type="hidden" id="change_company_doc_id">
                        </div>
                    </div>
                    
                    <!-- نمایش اطلاعات فعلی سند -->
                    <div id="change_company_info" style="display:none; background:#f8fafc; padding:10px; border-radius:8px; margin-bottom:10px; border:1px solid #e2e8f0;">
                        <div style="font-size:0.6rem; font-weight:600; color:#475569; margin-bottom:6px;">📄 اطلاعات فعلی سند</div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px; font-size:0.65rem;">
                            <div><span style="color:#94a3b8;">شماره سند:</span> <strong id="change_company_current_number">-</strong></div>
                            <div><span style="color:#94a3b8;">تاریخ سند:</span> <strong id="change_company_current_date">-</strong></div>
                            <div style="grid-column: span 2;"><span style="color:#94a3b8;">شرکت:</span> <strong id="change_company_current_company">-</strong></div>
                        </div>
                    </div>
                    
                    <!-- فیلدهای ویرایش -->
                    <div class="form-group">
                        <label>شماره سند جدید</label>
                        <input type="text" id="change_company_new_number" placeholder="شماره سند جدید">
                    </div>
                    
                    <div class="form-group">
                        <label>تاریخ سند جدید</label>
                        <input type="text" id="change_company_new_date" placeholder="1404/01/01" oninput="formatJalali(this)">
                    </div>
                    
                    <div class="form-group">
                        <label>شرکت جدید</label>
                        <select id="change_company_new_company">
                            <option value="">انتخاب کنید...</option>
                        </select>
                    </div>
                    
                    <button class="btn-manager" onclick="updateDocumentInfo()">💾 ذخیره تغییرات</button>
                    <div class="db-manager-status" id="change_company_status_msg"></div>
                </div>
            </div>
        </div>
        
        <!-- ===== تب 3: تغییر تاریخ تحویل ===== -->
        <div id="dbTab_delivery" class="db-tab-panel">
            <div class="db-manager-grid">
                <div class="db-manager-card" id="changeDeliveryDateCard">
                    <div class="card-header">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="card-title">تغییر تاریخ تحویل</span>
                    </div>
                    <div class="card-desc">تغییر تاریخ تحویل تمام اسناد یک کاربر</div>
                    
                    <div class="form-group">
                        <label>انتخاب کاربر</label>
                        <select id="change_delivery_user_id" onchange="loadUserLastDate('change_delivery', this.value)">
                            <option value="">در حال بارگذاری...</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="change_delivery_last_date_group" style="display:none;">
                        <label>تاریخ تحویل فعلی</label>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <button type="button" onclick="changeDeliveryDate('change_delivery', -1)" style="width:32px; height:32px; border-radius:8px; border:1.5px solid #e2e8f0; background:#f8fafc; cursor:pointer; font-size:1.2rem; font-weight:bold; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:all 0.2s;" onmouseover="this.style.background='#667eea'; this.style.color='white'; this.style.borderColor='#667eea';" onmouseout="this.style.background='#f8fafc'; this.style.color='#475569'; this.style.borderColor='#e2e8f0';">
                                <i class="fas fa-chevron-right" style="font-size:0.7rem;"></i>
                            </button>
                            <a href="#" id="change_delivery_current_date_link" onclick="goToDeliveryDate('change_delivery', event)" style="flex:1; padding:8px 12px; background:#f1f5f9; border-radius:8px; font-weight:600; text-decoration:none; color:#1e293b; border:1px solid #e2e8f0; transition:all 0.2s; cursor:pointer; text-align:center;" onmouseover="this.style.background='#e2e8f0'; this.style.borderColor='#667eea';" onmouseout="this.style.background='#f1f5f9'; this.style.borderColor='#e2e8f0';">
                                <span id="change_delivery_current_date">-</span>
                            </a>
                            <button type="button" onclick="changeDeliveryDate('change_delivery', 1)" style="width:32px; height:32px; border-radius:8px; border:1.5px solid #e2e8f0; background:#f8fafc; cursor:pointer; font-size:1.2rem; font-weight:bold; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:all 0.2s;" onmouseover="this.style.background='#667eea'; this.style.color='white'; this.style.borderColor='#667eea';" onmouseout="this.style.background='#f8fafc'; this.style.color='#475569'; this.style.borderColor='#e2e8f0';">
                                <i class="fas fa-chevron-left" style="font-size:0.7rem;"></i>
                            </button>
                        </div>
                        <div style="font-size:0.55rem; color:#94a3b8; margin-top:4px;">
                            تعداد اسناد: <span id="change_delivery_doc_count">0</span>
                            <span style="margin-right:10px;" id="change_delivery_date_counter">(تاریخ 1 از 1)</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>تاریخ تحویل جدید</label>
                        <input type="text" id="change_delivery_new_date" placeholder="1404/01/01" oninput="formatJalali(this)">
                    </div>
                    
                    <button class="btn-manager" onclick="changeUserDeliveryDate()">تغییر تاریخ</button>
                    <div class="db-manager-status" id="change_delivery_status"></div>
                </div>
            </div>
        </div>
    </div>
</div>
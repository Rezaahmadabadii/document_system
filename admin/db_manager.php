<!-- ========== مدیریت دیتابیس ========== -->
<div id="db_managerContent" class="left-content" style="display:none;">
    <div class="left-section-title">
        <i class="fas fa-database"></i> مدیریت دیتابیس
    </div>
    
    <div class="db-manager-grid">
        
        <!-- ===== کارت 1: برگشت امضای کاربر ===== -->
        <div class="db-manager-card" id="revertUserCard">
            <div class="card-header">
                <i class="fas fa-user-slash"></i>
                <span class="card-title">برگشت امضای کاربر</span>
            </div>
            <div class="card-desc">حذف امضای کاربر و بازگشت به حالت قبل از تایید</div>
            <div class="form-group">
                <label>انتخاب کاربر</label>
                <select id="revert_user_user_id">
                    <option value="">در حال بارگذاری...</option>
                </select>
            </div>
            <button class="btn-manager danger" onclick="revertUserSignature()">برگشت امضا</button>
            <div class="db-manager-status" id="revert_user_status"></div>
        </div>
        
        <!-- ===== کارت 2: برگشت امضای ادمین ===== -->
        <div class="db-manager-card" id="revertAdminCard">
            <div class="card-header">
                <i class="fas fa-user-check"></i>
                <span class="card-title">برگشت امضای ادمین</span>
            </div>
            <div class="card-desc">لغو تایید نهایی ادمین برای یک تاریخ تحویل</div>
            <div class="form-group">
                <label>انتخاب کاربر</label>
                <select id="revert_admin_user_id">
                    <option value="">در حال بارگذاری...</option>
                </select>
            </div>
            <div class="form-group">
                <label>تاریخ تحویل</label>
                <input type="text" id="revert_admin_date" placeholder="1404/01/01">
            </div>
            <button class="btn-manager danger" onclick="revertAdminSignature()">برگشت تایید</button>
            <div class="db-manager-status" id="revert_admin_status"></div>
        </div>
        
        <!-- ===== کارت 3: تغییر نام شرکت ===== -->
        <div class="db-manager-card" id="editCompanyCard">
            <div class="card-header">
                <i class="fas fa-building"></i>
                <span class="card-title">تغییر نام شرکت</span>
            </div>
            <div class="card-desc">ویرایش نام شرکت در دیتابیس</div>
            <div class="form-group">
                <label>انتخاب شرکت</label>
                <select id="edit_company_id">
                    <option value="">در حال بارگذاری...</option>
                </select>
            </div>
            <div class="form-group">
                <label>نام جدید</label>
                <input type="text" id="edit_company_new_name" placeholder="نام جدید شرکت">
            </div>
            <button class="btn-manager" onclick="editCompanyName()">تغییر نام</button>
            <div class="db-manager-status" id="edit_company_status"></div>
        </div>
        
        <!-- ===== کارت 4: حذف توضیحات سند ===== -->
        <div class="db-manager-card" id="deleteDescCard">
            <div class="card-header">
                <i class="fas fa-comment-slash"></i>
                <span class="card-title">حذف توضیحات سند</span>
            </div>
            <div class="card-desc">حذف توضیحات اضافه شده برای یک سند</div>
            <div class="form-group">
                <label>انتخاب سند</label>
                <select id="delete_desc_doc_id">
                    <option value="">در حال بارگذاری...</option>
                </select>
            </div>
            <button class="btn-manager danger" onclick="deleteDocumentDescription()">حذف توضیحات</button>
            <div class="db-manager-status" id="delete_desc_status"></div>
        </div>
        
        <!-- ===== کارت 5: تغییر تاریخ تحویل ===== -->
        <div class="db-manager-card" id="changeDateCard">
            <div class="card-header">
                <i class="fas fa-calendar-alt"></i>
                <span class="card-title">تغییر تاریخ تحویل</span>
            </div>
            <div class="card-desc">تغییر تاریخ تحویل یک سند</div>
            <div class="form-group">
                <label>انتخاب سند</label>
                <select id="change_date_doc_id">
                    <option value="">در حال بارگذاری...</option>
                </select>
            </div>
            <div class="form-group">
                <label>تاریخ جدید</label>
                <input type="text" id="change_date_new_date" placeholder="1404/01/01">
            </div>
            <button class="btn-manager" onclick="changeDeliveryDate()">تغییر تاریخ</button>
            <div class="db-manager-status" id="change_date_status"></div>
        </div>
        
    </div>
</div>
<!-- ========== پایان مدیریت دیتابیس ========== -->
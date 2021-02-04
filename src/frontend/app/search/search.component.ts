import { Component, OnInit, ViewChild, EventEmitter, ViewContainerRef, TemplateRef } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { MatDialogRef } from '@angular/material/dialog';
import { MatSidenav } from '@angular/material/sidenav';
import { ActivatedRoute } from '@angular/router';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { FunctionsService } from '@service/functions.service';
import { CriteriaToolComponent } from './criteria-tool/criteria-tool.component';
import { SearchResultListComponent } from './result-list/search-result-list.component';
import { NotificationService } from '@service/notification/notification.service';


@Component({
    templateUrl: 'search.component.html',
    styleUrls: ['search.component.scss']
})
export class SearchComponent implements OnInit {

    searchTerm: string = '';
    searchTemplateId: string = null;

    filtersChange = new EventEmitter();

    dialogRef: MatDialogRef<any>;
    loadingResult: boolean = false;

    @ViewChild('snav2', { static: true }) sidenavRight: MatSidenav;

    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild('appSearchResultList', { static: false }) appSearchResultList: SearchResultListComponent;
    @ViewChild('appCriteriaTool', { static: true }) appCriteriaTool: CriteriaToolComponent;

    constructor(
        _activatedRoute: ActivatedRoute,
        public translate: TranslateService,
        private headerService: HeaderService,
        public viewContainerRef: ViewContainerRef,
        public appService: AppService,
        public functions: FunctionsService,
        private notify: NotificationService
    ) {
        _activatedRoute.queryParams.subscribe(
            params => {
                if (!this.functions.empty(params.searchTemplateId)) {
                    this.searchTemplateId = params.searchTemplateId;
                    window.history.replaceState({}, document.title, window.location.pathname + window.location.hash.split('?')[0]);
                } else if (!this.functions.empty(params.value)) {
                    this.searchTerm = params.value;
                }
            }
        );
    }

    ngOnInit(): void {
        this.headerService.sideBarAdmin = true;
        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');
        this.headerService.setHeader(this.translate.instant('lang.searchMails'), '', '');
    }

    setLaunchWithSearchTemplate(templates: any) {
        if (this.searchTemplateId !== null) {
            const template = templates.find((itemTemplate: any) => itemTemplate.id == this.searchTemplateId);
            if (template !== undefined) {
                this.appCriteriaTool.selectSearchTemplate(template);
                this.appCriteriaTool.getCurrentCriteriaValues();
            } else {
                this.notify.error(this.translate.instant('lang.noTemplateFound'));
            }
        }
    }

    initSearch() {
        if (this.searchTemplateId === null) {
            this.appSearchResultList.initSavedCriteria();
        }
    }
}

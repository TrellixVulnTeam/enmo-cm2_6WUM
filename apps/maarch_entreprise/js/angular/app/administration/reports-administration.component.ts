import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { LANG } from '../translate.component';
import { NotificationService } from '../notification.service';

declare function $j (selector: any) : any;

declare var angularGlobals : any;


@Component({
    templateUrl : angularGlobals["reports-administrationView"],
    providers   : [NotificationService]
})
export class ReportsAdministrationComponent implements OnInit {

    coreUrl         : string;
    lang            : any       = LANG;

    groups          : any[]     = [];
    reports         : any[]     = [];
    selectedGroup   : string    = "";

    loading         : boolean   = false;


    constructor(public http: HttpClient, private notify: NotificationService) {
    }

    updateBreadcrumb(applicationName: string) {
        if ($j('#ariane')[0]) {
            $j('#ariane')[0].innerHTML = "<a href='index.php?reinit=true'>" + applicationName + "</a> > <a onclick='location.hash = \"/administration\"' style='cursor: pointer'>Administration</a> > Etats et edition";
        }
    }

    ngOnInit(): void {
        this.updateBreadcrumb(angularGlobals.applicationName);
        this.coreUrl = angularGlobals.coreUrl;

        this.loading = true;

        this.http.get(this.coreUrl + 'rest/reports/groups')
            .subscribe((data: any) => {
                this.groups = data['groups'];

                this.loading = false;
            }, () => {
                location.href = "index.php";
            });
    }

    loadReports() {
        this.http.get(this.coreUrl + 'rest/reports/groups/' + this.selectedGroup)
            .subscribe((data: any) => {
                this.reports = data['reports'];
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    onSubmit() {
        this.http.put(this.coreUrl + 'rest/reports/groups/' + this.selectedGroup, this.reports)
            .subscribe(() => {
                this.notify.success(this.lang.modificationSaved);
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }
}
import { Component, ViewChild, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { LANG } from '../../translate.component';
import { NotificationService } from '../../notification.service';
import { MatPaginator } from '@angular/material/paginator';
import { MatSidenav } from '@angular/material/sidenav';
import { MatSort } from '@angular/material/sort';
import { MatTableDataSource } from '@angular/material/table';
import { HeaderService } from '../../../service/header.service';
import { AppService } from '../../../service/app.service';
import { tap, finalize, catchError, filter, exhaustMap, map } from 'rxjs/operators';
import { of } from 'rxjs';
import { ConfirmComponent } from '../../../plugins/modal/confirm.component';
import { MatDialogRef, MatDialog } from '@angular/material/dialog';

declare function $j(selector: any): any;

@Component({
    templateUrl: "indexing-models-administration.component.html",
    styleUrls: ['indexing-models-administration.component.scss'],
    providers: [NotificationService, AppService]
})

export class IndexingModelsAdministrationComponent implements OnInit {

    @ViewChild('snav', { static: true }) public sidenavLeft: MatSidenav;
    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;

    lang: any = LANG;
    search: string = null;

    indexingModels: any[] = [];

    loading: boolean = false;

    displayedColumns = ['label', 'private', 'default', 'actions'];

    dataSource = new MatTableDataSource(this.indexingModels);

    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: true }) sort: MatSort;

    dialogRef: MatDialogRef<any>;

    applyFilter(filterValue: string) {
        filterValue = filterValue.trim(); // Remove whitespace
        filterValue = filterValue.toLowerCase(); // MatTableDataSource defaults to lowercase matches
        this.dataSource.filter = filterValue;
    }

    constructor(
        public http: HttpClient,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        private dialog: MatDialog,
    ) { }

    ngOnInit(): void {
        window['MainHeaderComponent'].setSnav(this.sidenavLeft);
        window['MainHeaderComponent'].setSnavRight(null);

        this.loading = true;

        this.http.get("../../rest/indexingModels").pipe(
            map((data: any) => {
                return data.indexingModels.filter((info: any) => info.private === false);
            }),
            tap((data: any) => {
                this.indexingModels = data;
                this.headerService.setHeader(this.lang.administration + ' ' + this.lang.indexingModels);
                setTimeout(() => {
                    this.dataSource = new MatTableDataSource(this.indexingModels);
                    this.dataSource.paginator = this.paginator;
                    this.dataSource.sort = this.sort;
                }, 0);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    delete(indexingModel: any) {

        this.dialogRef = this.dialog.open(ConfirmComponent, { autoFocus: false, disableClose: true, data: { title: this.lang.delete, msg: this.lang.confirmAction } });

        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../../rest/indexingModels/' + indexingModel.id)),
            tap((data: any) => {
                this.indexingModels = data.indexingModels;
                this.dataSource = new MatTableDataSource(this.indexingModels);
                this.dataSource.paginator = this.paginator;
                this.dataSource.sort = this.sort;
                this.notify.success(this.lang.indexingModelDeleted);
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();

    }
}
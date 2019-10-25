import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { LANG } from '../../app/translate.component';
import { Subject, Observable, of } from 'rxjs';
import { NotificationService } from '../notification.service';
import { MatDialog } from '@angular/material';
import { Router } from '@angular/router';
import { map, tap, filter, exhaustMap, catchError, finalize } from 'rxjs/operators';
import { ConfirmComponent } from '../../plugins/modal/confirm.component';


@Injectable()
export class FoldersService {

    lang: any = LANG;

    loading: boolean = true;

    pinnedFolders: any = [];

    folders: any = [];

    currentFolder: any = { id: 0 };

    private eventAction = new Subject<any>();

    constructor(
        public http: HttpClient,
        public dialog: MatDialog,
        private notify: NotificationService,
        private router: Router
    ) {
    }

    ngOnInit(): void { }

    initFolder() {
        this.currentFolder = { id: 0 };
    }

    catchEvent(): Observable<any> {
        return this.eventAction.asObservable();
    }

    goToFolder(folder: any) {
        this.setFolder(folder);
        this.router.navigate(['/folders/' + folder.id]);
    }

    setFolder(folder: any) {
        this.currentFolder = folder;
        this.eventAction.next(folder);
    }

    getCurrentFolder() {
        return this.currentFolder;
    }

    getFolders() {
        this.http.get("../../rest/folders").pipe(
            tap((data: any) => {
                this.folders = data.folders;
                this.eventAction.next({type:'initTree', content: ''});
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    getList() {
        return this.folders;
    }

    getPinnedFolders() {
        this.loading = true;

        this.http.get("../../rest/pinnedFolders").pipe(
            tap((data: any) => {
                this.pinnedFolders = data.folders;
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    setFolders(folders: any) {
        this.folders = folders;
    }

    getPinnedList() {
        return this.pinnedFolders;
    }

    pinFolder(folder: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { autoFocus: false, disableClose: true, data: { title: this.lang.pinFolder, msg: this.lang.confirmAction } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.post(`../../rest/folders/${folder.id}/pin`, {})),
            tap(() => {
                this.getPinnedFolders();
                this.notify.success(this.lang.folderPinned);
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    unpinFolder(folder: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { autoFocus: false, disableClose: true, data: { title: this.lang.unpinFolder, msg: this.lang.confirmAction } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete(`../../rest/folders/${folder.id}/unpin`)),
            tap(() => {
                this.getPinnedFolders();
                this.notify.success(this.lang.folderUnpinned);
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    getDragIds() {
        const treeList = this.folders.map((folder: any) => 'treefolder-list-' + folder.id);
        const list = this.pinnedFolders.map((folder: any) => 'folder-list-' + folder.id);

        return list.concat(treeList);
    }

    classifyDocument(ev: any, folder: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { autoFocus: false, disableClose: true, data: { title: this.lang.classify + ' ' + ev.item.data.alt_identifier, msg: this.lang.classifyQuestion + ' <b>' + ev.item.data.alt_identifier + '</b> ' + this.lang.in + ' <b>' + folder.label + '</b>&nbsp;?' } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.post(`../../rest/folders/${folder.id}/resources`, { resources: [ev.item.data.res_id] })),
            tap((data: any) => {


                if (this.pinnedFolders.filter((pinFolder: any) => pinFolder.id === folder.id)[0] !== undefined) {
                    this.pinnedFolders.filter((pinFolder: any) => pinFolder.id === folder.id)[0].countResources = data.countResources;
                }
                this.eventAction.next({type:'refreshFolder', content: {id: folder.id, countResources : data.countResources}});
            }),
            tap(() => {
                this.notify.success(this.lang.mailClassified);
                this.eventAction.next({type:'function', content: 'refreshDao'});
            }),
            finalize(() => folder.drag = false),
            catchError((err) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    getLoader() {
        return this.loading;
    }
}

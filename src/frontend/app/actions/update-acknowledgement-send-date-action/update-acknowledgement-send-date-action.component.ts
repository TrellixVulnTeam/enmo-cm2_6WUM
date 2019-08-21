import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { LANG } from '../../translate.component';
import { NotificationService } from '../../notification.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { NoteEditorComponent } from '../../notes/note-editor.component';

@Component({
    templateUrl: "update-acknowledgement-send-date-action.component.html",
    styleUrls: ['../close-mail-action/close-mail-action.component.scss'],
    providers: [NotificationService],
})
export class UpdateAcknowledgementSendDateActionComponent implements OnInit {

    lang: any = LANG;
    loading: boolean = false;

    @ViewChild('noteEditor', { static: true }) noteEditor: NoteEditorComponent;

    acknowledgementSendDate    : Date      = new Date();
    acknowledgementSendDateEnd : Date      = new Date();

    constructor(public http: HttpClient, private notify: NotificationService, public dialogRef: MatDialogRef<UpdateAcknowledgementSendDateActionComponent>, @Inject(MAT_DIALOG_DATA) public data: any) { }

    ngOnInit(): void { }

    onSubmit(): void {
        this.loading = true;
        this.http.put('../../rest/resourcesList/users/' + this.data.currentBasketInfo.ownerId + '/groups/' + this.data.currentBasketInfo.groupId + '/baskets/' + this.data.currentBasketInfo.basketId + '/actions/' + this.data.action.id, {resources : this.data.selectedRes, note : this.noteEditor.getNoteContent(), data : {send_date : (this.acknowledgementSendDate.getTime() / 1000).toString()}})
            .subscribe((data: any) => {
                this.loading = false;
                this.dialogRef.close('success');
            }, (err: any) => {
                this.notify.handleErrors(err);
                this.loading = false;
            });
    }
}

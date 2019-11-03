import { Component, OnInit, Input } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { LANG } from '../../translate.component';
import { catchError, tap, finalize } from 'rxjs/operators';
import { of } from 'rxjs';
import { NotificationService } from '../../notification.service';


@Component({
    selector: 'app-note-resume',
    templateUrl: "note-resume.component.html",
    styleUrls: [
        'note-resume.component.scss',
    ]
})

export class NoteResumeComponent implements OnInit {

    lang: any = LANG;

    loading: boolean = true;

    notes: any[] = [];

    @Input('resId') resId: number = null;

    constructor(
        public http: HttpClient,
        private notify: NotificationService,
    ) {
    }

    ngOnInit(): void {
        this.loading = true;
        this.loadNotes(this.resId);
    }

    loadNotes(resId: number) {
        this.http.get(`../../rest/resources/${resId}/notes?limit=2`).pipe(
            tap((data: any) => {
                this.notes = data.notes;
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
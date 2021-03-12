import { Component, OnInit } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { FormControl } from '@angular/forms';
import { catchError, debounceTime, exhaustMap, filter, finalize, map, tap } from 'rxjs/operators';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { MatDialog } from '@angular/material/dialog';
import { NotificationService } from '@service/notification/notification.service';
import { of } from 'rxjs';
import { KeyValue } from '@angular/common';
import { environment } from '../../../../environments/environment';



@Component({
    selector: 'app-maarch-to-maarch-parameters',
    templateUrl: './maarch-to-maarch-parameters.component.html',
    styleUrls: ['./maarch-to-maarch-parameters.component.scss'],
})
export class MaarchToMaarchParametersComponent implements OnInit {

    loading: boolean = true;
    doctypes: any = [];
    statuses: any = [];
    priorities: any = [];
    indexingModels: any = [];
    attachmentsTypes: any = [];

    basketToRedirect = new FormControl('NumericBasket');
    metadata: any = {
        typeId: new FormControl(),
        statusId: new FormControl(),
        priorityId: new FormControl(),
        indexingModelId: new FormControl(),
        attachmentTypeId: new FormControl(),
    };
    communications = {
        uri: new FormControl('https://cchaplin:maarch@demo.maarchcourrier.com'),
        email: new FormControl(null),
    };
    annuary = {
        enabled: new FormControl(false),
        organization: new FormControl('organization'),
        annuaries: [
            {
                uri: new FormControl('1.1.1.1'),
                baseDN: new FormControl('base'),
                login: new FormControl('Administrateur'),
                password: new FormControl('ThePassword'),
                ssl: new FormControl(false),
            }
        ]
    };
    maarch2maarchUrl: string = `https://docs.maarch.org/gitbook/html/MaarchCourrier/${environment.VERSION.split('.')[0] + '.' + environment.VERSION.split('.')[1]}/guat/guat_exploitation/maarch2maarch.html`;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private dialog: MatDialog,
        private notify: NotificationService
    ) { }

    async ngOnInit() {
        this.getDoctypes();
        this.getStatuses();
        this.getPriorities();
        this.getIndexingModels();
        this.getAttachmentTypes();
        await this.getConfiguration();
        this.loading = false;
    }

    getDoctypes() {
        return new Promise((resolve, reject) => {
            this.http.get(`../rest/doctypes`).pipe(
                tap((data: any) => {
                    let arrValues: any[] = [];
                    data.structure.forEach((doctype: any) => {
                        if (doctype['doctypes_second_level_id'] === undefined) {
                            arrValues.push({
                                id: doctype.doctypes_first_level_id,
                                label: doctype.doctypes_first_level_label,
                                title: doctype.doctypes_first_level_label,
                                disabled: true,
                                isTitle: true,
                                color: doctype.css_style
                            });
                            data.structure.filter((info: any) => info.doctypes_first_level_id === doctype.doctypes_first_level_id && info.doctypes_second_level_id !== undefined && info.description === undefined).forEach((secondDoctype: any) => {
                                arrValues.push({
                                    id: secondDoctype.doctypes_second_level_id,
                                    label: '&nbsp;&nbsp;&nbsp;&nbsp;' + secondDoctype.doctypes_second_level_label,
                                    title: secondDoctype.doctypes_second_level_label,
                                    disabled: true,
                                    isTitle: true,
                                    color: secondDoctype.css_style
                                });
                                arrValues = arrValues.concat(data.structure.filter((infoDoctype: any) => infoDoctype.doctypes_second_level_id === secondDoctype.doctypes_second_level_id && infoDoctype.description !== undefined).map((infoType: any) => {
                                    return {
                                        id: infoType.type_id,
                                        label: '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' + infoType.description,
                                        title: infoType.description,
                                        disabled: false,
                                        isTitle: false,
                                    };
                                }));
                            });
                        }
                    });
                    this.doctypes = arrValues;
                    resolve(true);
                })
            ).subscribe();
        });
    }

    getStatuses() {
        return new Promise((resolve, reject) => {
            this.http.get(`../rest/statuses`).pipe(
                tap((data: any) => {
                    this.statuses = data.statuses.map((status: any) => {
                        return {
                            id: status.identifier,
                            label: status.label_status
                        };
                    });
                    resolve(true);
                })
            ).subscribe();
        });
    }

    getPriorities() {
        return new Promise((resolve, reject) => {
            this.http.get(`../rest/priorities`).pipe(
                tap((data: any) => {
                    this.priorities = data.priorities;
                    resolve(true);
                })
            ).subscribe();
        });
    }

    getIndexingModels() {
        return new Promise((resolve, reject) => {
            this.http.get(`../rest/indexingModels`).pipe(
                tap((data: any) => {
                    this.indexingModels = data.indexingModels.filter((info: any) => info.private === false);
                    resolve(true);
                })
            ).subscribe();
        });
    }

    getAttachmentTypes() {
        return new Promise((resolve, reject) => {
            this.http.get(`../rest/attachmentsTypes`).pipe(
                tap((data: any) => {
                    Object.keys(data.attachmentsTypes).forEach(templateType => {
                        this.attachmentsTypes.push({
                            id: data.attachmentsTypes[templateType].id,
                            label: data.attachmentsTypes[templateType].label
                        });
                    });
                    resolve(true);
                })
            ).subscribe();
        });
    }

    getConfiguration() {
        return new Promise((resolve, reject) => {
            this.http.get(`../rest/m2mConfiguration`).pipe(
                map((data: any) => {
                    return data.configuration;
                }),
                tap((data: any) => {
                    Object.keys(this.communications).forEach(elemId => {
                        this.communications[elemId].setValue(data.communications[elemId]);
                        this.communications[elemId].valueChanges
                            .pipe(
                                debounceTime(300),
                                tap((value: any) => {
                                    this.saveConfiguration();
                                }),
                            ).subscribe();
                    });
                    Object.keys(this.metadata).forEach(elemId => {
                        this.metadata[elemId].setValue(data.metadata[elemId]);
                        this.metadata[elemId].valueChanges
                            .pipe(
                                debounceTime(300),
                                tap((value: any) => {
                                    this.saveConfiguration();
                                }),
                            ).subscribe();
                    });
                    Object.keys(this.annuary).forEach(elemId => {
                        if (['annuaries'].indexOf(elemId) === -1) {
                            this.annuary[elemId].setValue(data.annuary[elemId]);
                            this.annuary[elemId].valueChanges
                                .pipe(
                                    debounceTime(300),
                                    tap((value: any) => {
                                        if (elemId === 'enabled' && value === true && this.annuary.annuaries.length === 0) {
                                            this.addAnnuary();
                                        } else {
                                            this.saveConfiguration();
                                        }
                                    }),
                                ).subscribe();
                        } else {
                            this.annuary[elemId] = [];
                            data.annuary[elemId].forEach((annuaryConf: any, index: number) => {
                                this.annuary[elemId].push({});
                                Object.keys(annuaryConf).forEach(annuaryItem => {
                                    this.annuary[elemId][index][annuaryItem] = new FormControl(data.annuary[elemId][index][annuaryItem]);
                                    this.annuary[elemId][index][annuaryItem].valueChanges
                                        .pipe(
                                            debounceTime(300),
                                            tap((value: any) => {
                                                this.saveConfiguration();
                                            }),
                                        ).subscribe();
                                });
                            });
                        }
                    });
                    this.basketToRedirect.setValue(data.basketToRedirect);
                    this.basketToRedirect.valueChanges
                        .pipe(
                            debounceTime(300),
                            tap((value: any) => {
                                this.saveConfiguration();
                            }),
                        ).subscribe();
                }),
                finalize(() => this.loading = false),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    addAnnuary() {
        const newAnnuary = {
            uri: new FormControl('1.1.1.1'),
            baseDN: new FormControl('base'),
            login: new FormControl('Administrateur'),
            password: new FormControl('ThePassword'),
            ssl: new FormControl(false),
        };
        Object.keys(newAnnuary).forEach(annuaryItem => {
            newAnnuary[annuaryItem].valueChanges
                .pipe(
                    debounceTime(300),
                    tap((value: any) => {
                        this.saveConfiguration();
                    }),
                ).subscribe();
        });
        this.annuary.annuaries.push(newAnnuary);
        this.saveConfiguration();
    }

    deleteAnnuary(index: number) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            tap(() => {
                this.annuary.annuaries.splice(index, 1);
                this.saveConfiguration();
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }


    originalOrder = (a: KeyValue<string, any>, b: KeyValue<string, any>): number => {
        return 0;
    }

    saveConfiguration() {
        this.http.put(`../rest/m2mConfiguration`, { configuration: this.formatConfiguration() }).pipe(
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    formatConfiguration() {
        const config = {
            basketToRedirect: this.basketToRedirect.value,
            metadata: {},
            annuary: {
                annuaries: []
            },
            communications: {
                uri: this.communications.uri.value,
                email: this.communications.email.value
            },
        };
        Object.keys(this.metadata).forEach(elemId => {
            config['metadata'][elemId] = this.metadata[elemId].value;
        });
        Object.keys(this.annuary).forEach(elemId => {
            if (elemId !== 'annuaries') {
                config['annuary'][elemId] = this.annuary[elemId].value;
            } else {
                this.annuary[elemId].forEach((annuary: any, index: number) => {
                    const annuaryObj = {};
                    Object.keys(annuary).forEach(annuaryItem => {
                        annuaryObj[annuaryItem] = annuary[annuaryItem].value;
                    });
                    config['annuary'][elemId].push(annuaryObj);
                });
            }
        });
        return config;
    }
}
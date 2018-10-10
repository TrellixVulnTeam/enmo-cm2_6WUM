import { ChangeDetectorRef, Component, OnInit, ViewChild } from '@angular/core';
import { MediaMatcher } from '@angular/cdk/layout';
import { HttpClient } from '@angular/common/http';
import { Router, ActivatedRoute } from '@angular/router';
import { LANG } from '../../translate.component';
import { NotificationService } from '../../notification.service';
import { MatSidenav } from '@angular/material';

declare function $j(selector: any): any;

declare var angularGlobals: any;


@Component({
    templateUrl: "contacts-filling-administration.component.html",
    styleUrls: ['contacts-filling-administration.component.scss'],
    providers: [NotificationService]
})
export class ContactsFillingAdministrationComponent implements OnInit {
    /*HEADER*/
    titleHeader                              : string;
    @ViewChild('snav') public  sidenavLeft   : MatSidenav;
    @ViewChild('snav2') public sidenavRight  : MatSidenav;
    
    mobileQuery: MediaQueryList;
    private _mobileQueryListener: () => void;
    lang: any = LANG;
    coreUrl: string;

    contactsFilling: any = {
        'rating_columns' : [],
        'first_threshold' : '33',
        'second_threshold' : '66',
    };

    arrRatingColumns: String[] = [];
    fillingColor = {
        'first_threshold' : '#8e3e52',
        'second_threshold' : '#FF9740',
        'third_threshold' : '#ffffff',
        
    };
    fillingColumns = [
        'address_complement',
        'address_country',
        'address_num',
        'address_postal_code',
        'address_street',
        'address_town',
        'contact_firstname',
        'contact_function',
        'contact_lastname',
        'contact_other_data',
        'contact_title',
        'department',
        'email',
        'firstname',
        'function',
        'lastname',
        'occupancy',
        'other_data',
        'phone',
        'salutation_footer',
        'salutation_footer',
        'salutation_header',
        'society_sort',
        'society',
        'title',
        'website',
    ];
    fillingColumnsSelected = ['society'];

    loading: boolean = false;

    constructor(changeDetectorRef: ChangeDetectorRef, media: MediaMatcher, public http: HttpClient, private route: ActivatedRoute, private router: Router, private notify: NotificationService) {
        $j("link[href='merged_css.php']").remove();
        this.mobileQuery = media.matchMedia('(max-width: 768px)');
        this._mobileQueryListener = () => changeDetectorRef.detectChanges();
        this.mobileQuery.addListener(this._mobileQueryListener);
    }

    ngOnDestroy(): void {
        this.mobileQuery.removeListener(this._mobileQueryListener);
    }

    ngOnInit(): void {
        this.coreUrl = angularGlobals.coreUrl;

        this.loading = true;

        window['MainHeaderComponent'].refreshTitle(this.lang.contactsFillingAdministration);
        window['MainHeaderComponent'].setSnav(this.sidenavLeft);
        window['MainHeaderComponent'].setSnavRight(null);

        this.http.get(this.coreUrl + 'rest/contactsFilling')
            .subscribe((data: any) => {
                this.contactsFilling = data.contactsFilling;

                this.loading = false;
            });
    }

    addCriteria(event:any,criteria:String) {
        console.log(event);
        if (event.checked) {
            this.arrRatingColumns.push(criteria);
        } else {
            this.arrRatingColumns.splice(this.arrRatingColumns.indexOf(criteria), 1);
        }
        
        console.log(this.arrRatingColumns);
    }

    onSubmit() {
        this.http.put(this.coreUrl + 'rest/contactsFilling', this.contactsFilling)
            .subscribe(() => {
                this.router.navigate(['/administration/contacts-filling']);
                // this.notify.success(this.lang.contactsGroupUpdated);

            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }
}

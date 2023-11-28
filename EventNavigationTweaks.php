<?php
/**
 * Event Navigation Tweaks
 * 
 * Provides enhancements to navigation of events and arms on data entry forms, the "Add / Edit Records" page, the "Record Status Dashboard" page, and "Record Home" page.
 */
namespace MCRI\EventNavigationTweaks;

use ExternalModules\AbstractExternalModule;

class EventNavigationTweaks extends AbstractExternalModule
{
        public function redcap_data_entry_form_top($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance) {
                global $Proj, $lang;
                if (!\REDCap::isLongitudinal()) { return; }
                if( !$this->framework->getProjectSetting('data-entry-form-event-nav') ) { return; }
                
                $popoverContent = $this->makeEventNavPopoverContent($instrument);
                
                foreach (array('west-event-nav','center-event-nav') as $id) {
                        echo '<button id="'.$id.'" type="button" class="ml-1 btn btn-xs btn-outline-dark" data-bs-toggle="popover" data-bs-content="'.$popoverContent.'"><i class="fas fa-arrows-alt-h event-nav-icon"></i> '.$lang['dataqueries_95'].'</button>'; // "Change"
                }

                ?>
                <style type="text/css">
                    #west-event-nav, #center-event-nav { display: none; }
                    .popover { max-width: 750px; width: 750px; }
                    .event-nav-hdr { border-bottom: 1px solid #888; }
                    .event-nav-row { border-top: 1px solid #ddd; }
                    .event-nav-row-current { background-color: #fafafa; }
                    .event-nav-icon:hover { color: white; }
                </style>
                <script type="text/javascript">
                    $(document).ready(function() {
                        var wnav = $('#west-event-nav');
                        $('.menuboxsub:contains("Event:") > div:eq(1)').append(wnav);
                        wnav.show();
                        var cnav = $('#center-event-nav');
                        $('#contextMsg > div:eq(1) > span').append(cnav);
                        cnav.show();
                        $('[data-bs-toggle="popover"]').popover({
                            placement : 'bottom',
                            html : true,
                            title : 'Event Navigation: <a href="#" class="close p-1" data-dismiss="alert"><i class="fas fa-times"></i></a>'
                        });
                        $(document).on("click", ".popover .close" , function(){
                            $(this).parents(".popover").hide();//popover('hide');
                        });
                    });
                </script>
                <?php
        }
        
        public function redcap_every_page_top($project_id) {
                global $Proj;
                if (!$Proj->multiple_arms) { return; }
                if (PAGE==='DataEntry/record_home.php' && !isset($_GET['id'])) {
                        $this->includeAddEditRecordsPageContent();
                } else if (PAGE==='DataEntry/record_home.php' && isset($_GET['id'])) {
                        $this->includeRecordHomePageContent();
                } else if (PAGE==='DataEntry/record_status_dashboard.php' && !isset($_GET['id'])) {
                        $this->includeDashboardPageContent();
                }
        }
        
        protected function makeEventNavPopoverContent($currentInstrument='') {
                global $Proj, $lang;
                // read all form status values for current record
                $record = $this->escape($_GET['id']);
                $statusFields = array();
                
                $recordEvents = $this->getRecordEvents($record);
                $recordArms = array();
                $eventArmNum = array();
                
                foreach ($Proj->events as $armNum => $armAttr) {
                        // is record present in any of this arm's events?
                        if (count(array_intersect($recordEvents, array_keys($armAttr['events'])))> 0 ) {
                                $recordArms[] = $armNum;
                        }
                        foreach (array_keys($armAttr['events']) as $thisArmEvent) {
                                $eventArmNum[$thisArmEvent] = $armNum;
                        }
                }
                
                foreach (array_keys($Proj->forms) as $thisInstrument) {
                        $statusFields[] = $thisInstrument.'_complete';
                }
                $recordData = \REDCap::getData(array(
                    'return_format' => 'array',
                    'records' => $record,
                    'fields' => $statusFields
                ));
                
                $currentInstrumentName = \REDCap::getInstrumentNames($currentInstrument);
                
                $html = '<div class=\'container\'>';
                $html .= '<div class=\'row event-nav-hdr\'\'>';
                $html .= '<div class=\'col-6 font-weight-bold\'>'.$lang['global_10'].'</div>'; // "Event Name"
                $html .= '<div class=\'col-3 font-weight-bold\'>'.$lang['global_89'].' 1</div>'; // "Instrument"
                $html .= '<div class=\'col-3 font-weight-bold\'>'.\REDCap::escapeHtml($currentInstrumentName).'</div>';
                $html .= '</div>';
                
                foreach ($Proj->events as $thisArmNum => $thisArmAttr) {

                        if (!in_array($thisArmNum, $recordArms)) { continue; } // skip events in arms where record does not exist
                        
                        foreach (array_keys($thisArmAttr['events']) as $eventId) {
                                $eventData = (is_array($recordData[$record][$eventId])) ? $recordData[$record][$eventId] : array();
                                $eventFirstInstrument = $Proj->eventsForms[$eventId][0];
                                $eventFirstInstrumentStatus = (array_key_exists($eventFirstInstrument.'_complete', $eventData)) ? $eventData[$eventFirstInstrument.'_complete'] : '';
                                $currentInstrumentStatus = (array_key_exists($currentInstrument.'_complete', $eventData)) ? $eventData[$currentInstrument.'_complete'] : '';

                                $html .= $this->makeNavRow($record, $eventId, $eventFirstInstrument, $eventFirstInstrumentStatus, $currentInstrument, $currentInstrumentStatus);
                        }
                }
                
                $html .= '</div>';
                
                return \REDCap::filterHtml($html);

        }

        protected function makeNavRow($record, $eventId, $eventFirstInstrument, $eventFirstInstrumentStatus, $currentInstrument, $currentInstrumentStatus) {
                global $Proj;
                $eventName = \REDCap::getEventNames(false, true, $eventId);
                $formName = \REDCap::getInstrumentNames($currentInstrument);

                $linkFirst = $this->getStatusIconLink($record, $eventId, $eventFirstInstrument, $eventFirstInstrumentStatus);
                if (in_array($currentInstrument, $Proj->eventsForms[$eventId])) {
                        $linkCurrent = $this->getStatusIconLink($record, $eventId, $currentInstrument, $currentInstrumentStatus);
                } else {
                        $linkCurrent = '';
                }
                $currentEvent = ($eventId==$_GET['event_id']) ? 'event-nav-row-current' : '';
                
                $html = '<div class=\'row event-nav-row '.$currentEvent.'\'>';
                $html .= '<div class=\'col-6\'>'.\REDCap::escapeHtml($eventName).'</div>';
                $html .= '<div class=\'col-3\'>'.$linkFirst.'</div>';
                $html .= '<div class=\'col-3\'>'.$linkCurrent.'</div>';
                $html .= '</div>';
                return \REDCap::filterHtml($html);
        }
        
        protected function getStatusIconLink($record, $eventId, $instrument, $statusValue) {
                switch ($statusValue) {
                    case '2':
                        $title = 'Complete';
                        $circle = 'circle_green';
                        break;
                    case '1':
                        $title = 'Unverified';
                        $circle = 'circle_yellow';
                        break;
                    case '0':
                        // is really "incomplete" or actually "no data saved"?
                        $redcap_data = method_exists('\REDCap', 'getDataTable') ? \REDCap::getDataTable($this->getProjectId()) : "redcap_data"; 
                        $sql = "select `value` from $redcap_data where project_id=? and record=? and event_id=? and field_name=? and coalesce(instance, '1')=1 and `value`='0'";
                        $q = $this->query($sql, [$this->getProjectId(), $record, $eventId, $instrument.'_complete']);
                        $circle = ($q->num_rows) ? 'circle_red' : 'circle_gray';
                        break;
                    default:
                        $title = 'Incomplete';
                        $circle = 'circle_gray';
                        break;
                }
                $html = "<a title='$title' href='".APP_PATH_WEBROOT."DataEntry/index.php?pid=".PROJECT_ID."&page=$instrument&id=$record&event_id=$eventId'><img src='".APP_PATH_IMAGES."$circle.png' style='height:16px;width:16px;'></a>";
                return \REDCap::filterHtml($html);
        }
        
        /**
         * Multi-Arm Projects Add/Edit Records page
         * - Hide arm selection dropdown lists.
         * - Remove reference to "on arm selected" from Add button label
         */
        protected function includeAddEditRecordsPageContent() {
                global $Proj, $lang;
                if( !$this->framework->getProjectSetting('primary-arm') ) { return; }
                // hide arm selection dropdown lists 
                // select/enter record in first arm only
                ?>
                <style type="text/css">
                    #arm_name, #arm_name_newid { display: none; }
                </style>
                <?php
                if ($Proj->project['auto_inc_set']) {
                        ?>
                        <script type="text/javascript">
                            $(document).ready(function() {
                                // change "+ Add new record for the arm selected above"
                                // to just "+ Add new record"
                                var newLbl = '<i class="fas fa-plus"></i> <?php echo js_escape($lang['data_entry_46']); ?>';
                                var addBtn = $('button:contains("<?php echo js_escape($lang['data_entry_46']); ?>")');
                                addBtn.html(newLbl);
                            });
                        </script>
                        <?php
                }
        }
        
        /**
         * Multi-Arm Projects Record Stataus Dashboard page
         * - Hide record create input/buttons except on first arm.
         */
        protected function includeDashboardPageContent() {
                global $Proj, $lang;
                $currentArm = getArm();
                if( $this->framework->getProjectSetting('primary-arm') &&
                    $currentArm != $Proj->firstArmNum ) {
                        // hide new record option if not first arm
                        ?>
                        <script type="text/javascript">
                            $(document).ready(function() {
                                var autonumbering = <?php echo $Proj->project['auto_inc_set'];?>;
                                if (autonumbering) {
                                    // hide "+Add new record for this arm" button
                                    $('button:contains("<?php echo js_escape($lang['data_entry_46']); ?>")').parent('div').hide();
                                } else {
                                    // hide input and "+Create" button
                                    $('#inputString').parent('div').hide();
                                }
                            });
                        </script>
                        <?php
                }
        }
        
        /**
         * Multi-Arm Projects Record Home page (with record selected)
         * - Suppress the "NOTICE: Record ID '?' exists in another arm." message
         * - Include arm navigation bar.
         */
        protected function includeRecordHomePageContent() {
                global $Proj, $lang;
                if( !$this->framework->getProjectSetting('record-home-arm-nav') ) { return; }
                echo $this->makeArmNavBar();
                ?>
                <style type="text/css">
                    p.red { display: none; }
                    #armnav { display: none; margin: 0; max-width: 800px; }
                </style>
                <script type="text/javascript">
                    $(document).ready(function() {
                        $('p.red')
                            .not(':contains("<?php echo $lang['grid_37'];?>")')
                            .show();
                        $('#armnav').detach().insertBefore('#record_display_name').show();
                    });
                </script>
                <?php
        }

        protected function makeArmNavBar() {
                global $Proj, $lang;
                $textArm = $lang['global_08']; // "Arm"
                $textArms = '<i class="fas fa-arrows-alt-h"></i> '.$lang['api_97'];
                $textAdd = '<i class="fas fa-plus"></i> '.$lang['design_171']; // "Add"
                $textView = '<i class="fas fa-list-alt"></i> '.$lang['global_84']; // "View"
                
                $armnav = '<div id="armnav" class="gray2 container"><div class="row">';
                $armnav .= '<div class="col">'
                        . '<span style="color:#000066;font-size:16px;">'
                        .$textArms
                        .'</span>'
                        .'</div>';
                
                $currentArmNum = getArm();
                
                $record = $_GET['id'];
                $recordEvents = $this->getRecordEvents($record);
                // MGB Code modification
                // Add control to which Arms can be displayed
                $standardMode = false;
                if(is_null($this->getSubSettings("display-conditions")[0]["display-arms"])) {
                    $standardMode = true;
                } else {
                    $armnav = $this->armControl($record, $textArms,$Proj,$recordEvents,$textView,$textArm,$textAdd,$currentArmNum);
                }
                if(!$standardMode){
                    return \REDCap::filterHtml($armnav);
                }
                // End of MGB Code modification
                foreach ($Proj->events as $armNum => $armAttr) {
                        $breakAfterThis = false;
                        $armnav .= '<div class="col">';
                        
                        // is record present in any of this arm's events?
                        if (count(array_intersect($recordEvents, array_keys($armAttr['events'])))> 0 ) {
                                $btnClass = "btn-primaryrc";
                                $btnLbl = "$textView $textArm $armNum: {$armAttr['name']}";
                        } else {
                                $btnClass = "btn-success";
                                $btnLbl = "$textAdd $textArm $armNum: {$armAttr['name']}";
                                
                                // if limiting record creation to first arm only
                                // and record does not yet exist in first arm
                                // then brak after this loop iteration
                                if ($this->framework->getProjectSetting('primary-arm') &&
                                    $armNum == $Proj->firstArmNum) {
                                        $breakAfterThis = true;
                                }
                        }
                        
                        if ($armNum == $currentArmNum) {
                                $btnClass = 'btn-secondary disabled'; // button disabled for current arm
                                $btnLbl = "$textArm $armNum: {$armAttr['name']}";
                                $btnHref = '#';
                        } else {
                                $btnHref = "./record_home.php?pid={$Proj->project_id}&arm=$armNum&id=$record";
                        }

                        $btn = "<a class=\"btn $btnClass\" href=\"$btnHref\" style=\"color:white;\">";
                        $btn .= $btnLbl;
                        $btn .= '</a>';
                        $armnav .= $btn;
                        
                        $armnav .= '</div>';

                        if ($breakAfterThis) { break; }
                }
                
                $armnav .= '</div></div>';
                return \REDCap::filterHtml($armnav);
        }
        
        /**
         * Get array of event ids that the current record has data for 
         * @return array Event ids that the current record has data for 
         */
        protected function getRecordEvents($record) {
                global $Proj;
                $recordData = \REDCap::getData(array(
                    'return_format' => 'array',
                    'records' => $record,
                    'fields' => $Proj->table_pk
                ));

                if ($recordData[$record] == null) {
                        return $events;  // null ??? is it useful or safe on the calling end, if we get a null, of getRecordEvents
                }                
                
                $events = array();

                foreach (array_keys($recordData[$record]) as $eventId) {
                        if (is_numeric($eventId)) { $events[] = $eventId; }
                }
                return $events;
        }

    // MGB Code modification
    protected function armControl($record, $textArms,$Proj,$recordEvents,$textView,$textArm,$textAdd,$currentArmNum)
    {
        $triggerField = $this->framework->getProjectSetting('display-arms');

        $params = [
            'return_format' => 'array',
            'records' => $record,
            'fields' => $triggerField
        ];

        $res = \REDCap::getData($params);
        $armnav = '<div id="armnav" class="container"><div class="row"> <div class="col text-right">';

        $armnav .= "</div></div><div class=\"gray2 row\">";
        $armnav .= '<div class="col-2">'
            . '<span style="color:#000066;font-size:16px;">'
            . $textArms
            . '</span>'
            . '</div><div class="col-10">';

        foreach ($triggerField as $key => $placeholder) {
            $triggerFieldValues[$key] = $res[$record][$this->framework->getProjectSetting('corresponding-event')[$key]][$triggerField[$key]];
        }

        $listOfArms = $this->genArmListBucket($Proj);

        foreach ($triggerFieldValues as $keyCond => $selectedValues) {
            $selectedArms = strpos($selectedValues, ',') === false ? array(0 => $selectedValues) : explode(",", $selectedValues);
            foreach ($Proj->events as $armNum => $armAttr) {
                // Add control to which arms are displayed
                if (in_array($armNum, $selectedArms) && $listOfArms[$armNum] == FALSE) {
                    // Flag Arm array as already being included in $armnav so it's not included a second time
                    $listOfArms[$armNum] = TRUE;

                    $breakAfterThis = false;

                    // is record present in any of this arm's events?
                    if (count(array_intersect($recordEvents, array_keys($armAttr['events']))) > 0) {
                        $btnClass = "btn-primaryrc";
                        $btnLbl = "$textView $textArm $armNum: {$armAttr['name']}";
                    } else {
                        $btnClass = "btn-success";
                        $btnLbl = "$textAdd $textArm $armNum: {$armAttr['name']}";

                        // if limiting record creation to first arm only
                        // and record does not yet exist in first arm
                        // then break after this loop iteration
                        if ($this->framework->getProjectSetting('primary-arm') &&
                            $armNum == $Proj->firstArmNum) {
                            $breakAfterThis = true;
                        }
                    }

                    if ($armNum == $currentArmNum) {
                        $btnClass = 'btn-secondary disabled'; // button disabled for current arm
                        $btnLbl = "$textArm $armNum: {$armAttr['name']}";
                        $btnHref = '#';
                    } else {
                        $btnHref = "./record_home.php?pid={$Proj->project_id}&arm=$armNum&id=" . htmlspecialchars($record, ENT_QUOTES, 'UTF-8');
                    }

                    $btn = "<a class=\"btn $btnClass\" href=\"$btnHref\" style=\"color:white;margin: 5px\">";
                    $btn .= $btnLbl;
                    $btn .= '</a>';
                    $listOfArmsButtons[$armNum] = $btn;

                    if ($breakAfterThis) {
                        break;
                    }
                }
            }

            // Add the study arms configured as on-demand (that is, study arms available by default).
            $selectedArmsIDs = $this->framework->getProjectSetting('on-demand-arm-names');
            foreach ($Proj->events as $armNum => $armAttr) {
                // Add control to which arms are displayed
                if (in_array($armAttr['id'], $selectedArmsIDs) && $listOfArms[$armNum] == FALSE) {
                    // Flag Arm array as already being included in $armnav so it's not included a second time
                    $listOfArms[$armNum] = true;

                    $breakAfterThis = false;

                    // is record present in any of this arm's events?
                    if (count(array_intersect($recordEvents, array_keys($armAttr['events']))) > 0) {
                        $btnClass = "btn-primaryrc";
                        $btnLbl = "$textView $textArm $armNum: {$armAttr['name']}";
                    } else {
                        $btnClass = "btn-success";
                        $btnLbl = "$textAdd $textArm $armNum: {$armAttr['name']}";

                        // if limiting record creation to first arm only
                        // and record does not yet exist in first arm
                        // then break after this loop iteration
                        if ($this->framework->getProjectSetting('primary-arm') &&
                            $armNum == $Proj->firstArmNum) {
                            $breakAfterThis = true;
                        }
                    }

                    if ($armNum == $currentArmNum) {
                        $btnClass = 'btn-secondary disabled'; // button disabled for current arm
                        $btnLbl = "$textArm $armNum: {$armAttr['name']}";
                        $btnHref = '#';
                    } else {
                        $btnHref = "./record_home.php?pid={$Proj->project_id}&arm=$armNum&id=".$this->escape($record);
                    }

                    $btn = "<a class=\"btn $btnClass\" href=\"$btnHref\" style=\"color:white;margin: 5px\">";
                    $btn .= $btnLbl;
                    $btn .= '</a>';

                    $listOfArmsButtons[$armNum] = $btn;

                    if ($breakAfterThis) {
                        break;
                    }
                }
            }
        }

        if( !is_null($listOfArmsButtons)) {
            // Print sorted list of buttons into $armnav
            ksort($listOfArmsButtons);
            foreach ($listOfArmsButtons as $item => $buttonScript) {
                $armnav .= $buttonScript;
            }
        }

        return $armnav .= '</div></div></div>';
    }
    protected function genArmListBucket($Proj)
    {
        foreach ($Proj->events as $armNum => $armAttr) {
            $armsListBucket[$armNum] = FALSE;
        }
        return $armsListBucket;
    }
    // End of MGB Code modification
    
}

/* Ąžuolyno tvarkaraštis — Admin JS v3.1 */
(function($){
'use strict';
var SA={
  nonce:ss_admin.nonce,
  days:ss_admin.days,
  url:ss_admin.ajax_url,

  init:function(){
    this.modals();
    this.colorPickers();
    this.teachers();
    this.rooms();
    this.lessons();
    this.classes();
    this.students();
    this.importFlow();
    this.notice();
    this.bulk();
  },

  showNotice:function(msg,type){
    var $n=$('#ss-notice');
    $n.removeClass('notice-success notice-error is-dismissible')
      .addClass('notice-'+(type||'success')+' is-dismissible').find('p').text(msg);
    $n.show();$('html,body').animate({scrollTop:0},300);
    if(type!=='error') setTimeout(function(){$n.fadeOut();},4000);
  },
  notice:function(){
    $(document).on('click','.notice-dismiss',function(){$(this).closest('.notice').hide();});
  },

  /* ═══ MODALS ═══ */
  modals:function(){
    $(document).on('click','.ss-close-modal',function(){ $(this).closest('.ss-modal-overlay').hide(); });
    $(document).on('keydown',function(e){ if(e.key==='Escape'){ $('.ss-modal-overlay:visible').hide(); if(SA.$picker) SA.closePicker(); } });
    $(document).on('click','.ss-modal-overlay',function(e){ if($(e.target).is('.ss-modal-overlay')) $(this).hide(); });
  },

  /* ═══ COLOR PICKERS ═══ */
  colorPickers:function(){
    $(document).on('click','.ss-swatch',function(){
      SA.setColor($(this).closest('.ss-cpicker'),$(this).data('color'));
    });
    $(document).on('input change','.ss-native-color',function(){
      var c=$(this).val(),$w=$(this).closest('.ss-cpicker');
      $w.find('.ss-hex-input').val(c);$w.find('.ss-color-val').val(c);$w.find('.ss-swatch').removeClass('active');
    });
    $(document).on('input','.ss-hex-input',function(){
      var v=$(this).val().trim(),$w=$(this).closest('.ss-cpicker');
      if(/^#[0-9A-Fa-f]{6}$/.test(v)){
        $w.find('.ss-native-color').val(v);$w.find('.ss-color-val').val(v);
        $w.find('.ss-swatch').removeClass('active');$w.find('.ss-swatch[data-color="'+v+'"]').addClass('active');
      }
    });
  },
  setColor:function($w,c){
    $w.find('.ss-swatch').removeClass('active');$w.find('.ss-swatch[data-color="'+c+'"]').addClass('active');
    $w.find('.ss-native-color').val(c);$w.find('.ss-hex-input').val(c);$w.find('.ss-color-val').val(c);
  },
  getColor:function($w){ return $w.find('.ss-color-val').val()||'#4F8952'; },
  initColor:function($w,c){ SA.setColor($w,c||'#4F8952'); },

  /* ═══ TEACHERS ═══ */
  teachers:function(){
    var $m=$('#ss-modal-teacher');if(!$m.length)return;
    var $cp=$m.find('.ss-cpicker');
    $(document).on('click','.ss-open-modal[data-modal="teacher"]',function(){
      $('#ss-modal-teacher-title').text('Pridėti mokytoją');$('#ss-teacher-id').val('');
      $m.find('input[type=text],input[type=email]').val('');SA.initColor($cp,'#4F8952');$m.show();
      setTimeout(function(){$('#ss-teacher-name').focus();},100);
    });
    $(document).on('click','.ss-edit-teacher',function(){
      var $b=$(this);$('#ss-modal-teacher-title').text('Redaguoti mokytoją');
      $('#ss-teacher-id').val($b.data('id'));$('#ss-teacher-name').val($b.data('name'));
      $('#ss-teacher-short').val($b.data('short'));$('#ss-teacher-email').val($b.data('email'));
      SA.initColor($cp,$b.data('color'));$m.show();
    });
    $('#ss-cancel-teacher').on('click',function(){$m.hide();});
    $('#ss-save-teacher').on('click',function(){
      var name=$('#ss-teacher-name').val().trim();
      if(!name){alert('Vardas privalomas!');return;}
      var $btn=$(this);$btn.prop('disabled',true).text('Saugoma...');
      $.post(SA.url,{action:'ss_save_teacher',nonce:SA.nonce,id:$('#ss-teacher-id').val(),
        name:name,short:$('#ss-teacher-short').val().trim(),
        email:$('#ss-teacher-email').val().trim(),color:SA.getColor($cp)},
      function(res){
        $btn.prop('disabled',false).text('Išsaugoti');
        if(res.success){SA.showNotice('Mokytojas išsaugotas!');$m.hide();setTimeout(function(){location.reload();},600);}
        else SA.showNotice((res.data&&res.data.message)||'Klaida.','error');
      });
    });
    $(document).on('click','.ss-delete-teacher',function(){
      var name=$(this).data('name');if(!confirm('Ištrinti "'+name+'"?'))return;
      var id=$(this).data('id');
      $.post(SA.url,{action:'ss_delete_teacher',nonce:SA.nonce,id:id},function(res){
        if(res.success){$('#ss-teacher-row-'+id).fadeOut(250,function(){$(this).remove();});SA.showNotice('Ištrinta!');}
        else SA.showNotice('Klaida.','error');
      });
    });
  },

  /* ═══ ROOMS (KABINETAI) ═══ */
  rooms:function(){
    var $m=$('#ss-modal-room');if(!$m.length)return;
    var $cp=$m.find('.ss-cpicker');
    $(document).on('click','.ss-open-modal[data-modal="room"]',function(){
      $('#ss-modal-room-title').text('Pridėti kabinetą');$('#ss-room-id').val('');
      $m.find('input[type=text]').val('');SA.initColor($cp,'#224768');$m.show();
      setTimeout(function(){$('#ss-room-name').focus();},100);
    });
    $(document).on('click','.ss-edit-room',function(){
      var $b=$(this);$('#ss-modal-room-title').text('Redaguoti kabinetą');
      $('#ss-room-id').val($b.data('id'));$('#ss-room-name').val($b.data('name'));
      SA.initColor($cp,$b.data('color'));$m.show();
    });
    $('#ss-cancel-room').on('click',function(){$m.hide();});
    $('#ss-save-room').on('click',function(){
      var name=$('#ss-room-name').val().trim();
      if(!name){alert('Pavadinimas privalomas!');return;}
      var $btn=$(this);$btn.prop('disabled',true).text('Saugoma...');
      $.post(SA.url,{action:'ss_save_room',nonce:SA.nonce,id:$('#ss-room-id').val(),
        name:name,color:SA.getColor($cp)},
      function(res){
        $btn.prop('disabled',false).text('Išsaugoti');
        if(res.success){SA.showNotice('Kabinetas išsaugotas!');$m.hide();setTimeout(function(){location.reload();},600);}
        else SA.showNotice((res.data&&res.data.message)||'Klaida.','error');
      });
    });
    $(document).on('click','.ss-delete-room',function(){
      var name=$(this).data('name');if(!confirm('Ištrinti "'+name+'"?'))return;
      var id=$(this).data('id');
      $.post(SA.url,{action:'ss_delete_room',nonce:SA.nonce,id:id},function(res){
        if(res.success){$('#ss-room-row-'+id).fadeOut(250,function(){$(this).remove();});SA.showNotice('Ištrinta!');}
        else SA.showNotice('Klaida.','error');
      });
    });
  },

  /* ═══ LESSONS ═══ */
  lessons:function(){
    var $m=$('#ss-modal-lesson');if(!$m.length)return;
    var $cp=$m.find('.ss-cpicker');
    $(document).on('click','.ss-open-modal[data-modal="lesson"]',function(){
      $('#ss-modal-lesson-title').text('Pridėti pamoką');$('#ss-lesson-id').val('');
      $('#ss-lesson-subject').val('');$('#ss-lesson-teacher').val('');
      SA.initColor($cp,'#6FA672');$m.show();setTimeout(function(){$('#ss-lesson-subject').focus();},100);
    });
    $(document).on('click','.ss-edit-lesson',function(){
      var $b=$(this);$('#ss-modal-lesson-title').text('Redaguoti pamoką');
      $('#ss-lesson-id').val($b.data('id'));$('#ss-lesson-subject').val($b.data('subject'));
      $('#ss-lesson-teacher').val($b.data('teacher'));SA.initColor($cp,$b.data('color'));$m.show();
    });
    $('#ss-save-lesson').on('click',function(){
      var s=$('#ss-lesson-subject').val().trim();if(!s){alert('Dalykas privalomas!');return;}
      var $btn=$(this);$btn.prop('disabled',true).text('Saugoma...');
      $.post(SA.url,{action:'ss_save_lesson',nonce:SA.nonce,id:$('#ss-lesson-id').val(),
        subject:s,teacher_id:$('#ss-lesson-teacher').val(),color:SA.getColor($cp)},
      function(res){
        $btn.prop('disabled',false).text('Išsaugoti');
        if(res.success){SA.showNotice('Pamoka išsaugota!');$m.hide();setTimeout(function(){location.reload();},600);}
        else SA.showNotice((res.data&&res.data.message)||'Klaida.','error');
      });
    });
    $(document).on('click','.ss-delete-lesson',function(){
      var name=$(this).data('name');if(!confirm('Ištrinti "'+name+'"?'))return;
      var id=$(this).data('id');
      $.post(SA.url,{action:'ss_delete_lesson',nonce:SA.nonce,id:id},function(res){
        if(res.success){$('#ss-lesson-row-'+id).fadeOut(250,function(){$(this).remove();});SA.showNotice('Ištrinta!');}
        else SA.showNotice('Klaida.','error');
      });
    });
  },

  /* ═══ CLASSES & SCHEDULE GRID ═══ */
  classes:function(){
    var $gridModal=$('#ss-modal-grid');
    if(!$gridModal.length)return;
    var $classModal=$('#ss-modal-class');
    var $cp=$classModal.length?$classModal.find('.ss-cpicker'):$();
    if($cp.length) SA.initColor($cp,'#224768');
    var currentClassId=null;
    var currentGrid={};

    // ── Open Add modal ──
    $(document).on('click','.ss-open-modal[data-modal="class"]',function(){
      $('#ss-modal-class-title').text('Pridėti tvarkaraštį');
      $('#ss-class-id').val('');$('#ss-class-name').val('');$('#ss-class-is-imp').prop('checked',false);
      SA.initColor($cp,'#224768');$classModal.show();
      setTimeout(function(){$('#ss-class-name').focus();},100);
    });

    // ── Edit button ──
    $(document).on('click','.ss-edit-class',function(){
      var $b=$(this);
      $('#ss-modal-class-title').text('Redaguoti: '+$b.data('name'));
      $('#ss-class-id').val($b.data('id'));$('#ss-class-name').val($b.data('name'));
      $('#ss-class-is-imp').prop('checked',$b.data('is-imp')==='1'||$b.data('is-imp')===1);
      SA.initColor($cp,$b.data('color'));$classModal.show();
    });

    // ── Save class ──
    $('#ss-save-class').on('click',function(){
      var name=$('#ss-class-name').val().trim();
      if(!name){alert('Pavadinimas privalomas!');return;}
      var $btn=$(this);$btn.prop('disabled',true).text('Saugoma…');
      var isImp=$('#ss-class-is-imp').is(':checked')?1:0;
      $.post(SA.url,{action:'ss_save_class',nonce:SA.nonce,id:$('#ss-class-id').val(),
        name:name,color:SA.getColor($cp),is_imp:isImp},
      function(res){
        $btn.prop('disabled',false).text('Išsaugoti');
        if(res.success){SA.showNotice('Tvarkaraštis išsaugotas!');$classModal.hide();setTimeout(function(){location.reload();},600);}
        else SA.showNotice((res.data&&res.data.message)||'Klaida.','error');
      });
    });

    // ── Delete single ──
    $(document).on('click','.ss-delete-class',function(){
      var name=$(this).data('name');
      if(!confirm('Ištrinti "'+name+'"? Bus ištrinti ir tvarkaraščiai!'))return;
      var id=$(this).data('id');
      $.post(SA.url,{action:'ss_delete_class',nonce:SA.nonce,id:id},function(res){
        if(res.success){$('#ss-cls-row-'+id).fadeOut(250,function(){$(this).remove();});SA.showNotice('Klasė ištrinta!');}
        else SA.showNotice('Klaida.','error');
      });
    });

    // ── Open grid editor ──
    $(document).on('click','.ss-open-grid',function(){
      currentClassId=$(this).data('id');currentGrid={};
      $('#ss-grid-class-name').text($(this).data('name'));
      $('#ss-grid-code').text('--------');
      $('#ss-grid-loading').show();$('#ss-grid-wrap').hide();$gridModal.show();
      $.post(SA.url,{action:'ss_admin_get_class_schedule',nonce:SA.nonce,class_id:currentClassId},function(res){
        if(res.success){
          $.each(res.data.grid,function(day,periods){
            if(!currentGrid[day])currentGrid[day]={};
            $.each(periods,function(pid,entries){
              var arr=$.isArray(entries)?entries:[entries];
              $.each(arr,function(i,e){
                if(!e)return;
                e.is_free=(e.is_free===true||e.is_free===1||e.is_free==='1'||e.lesson_id===0||e.lesson_id==='0');
              });
              currentGrid[day][pid]=arr;
            });
          });
          if(res.data.code)$('#ss-grid-code').text(res.data.code);
        }
        $('#ss-grid-loading').hide();$('#ss-grid-wrap').show();SA.buildGrid();
      }).fail(function(){$('#ss-grid-loading').html('<span style="color:#D05155"><span class="dashicons dashicons-warning" style="font-size:16px;width:16px;height:16px;vertical-align:text-bottom"></span> Klaida kraunant tvarkaraštį.</span>');});
    });

    $('#ss-copy-grid-code').on('click',function(){
      var code=$('#ss-grid-code').text();
      if(code&&code!=='--------') navigator.clipboard.writeText(code).then(function(){SA.showNotice('Kodas nukopijuotas: '+code);});
    });
    $('#ss-regen-grid-code').on('click',function(){
      if(!confirm('Sugeneruoti naują kodą? Senas kodas nustos veikti.'))return;
      var $btn=$(this);$btn.prop('disabled',true);
      $.post(SA.url,{action:'ss_regenerate_class_code',nonce:SA.nonce,class_id:currentClassId},function(res){
        $btn.prop('disabled',false);
        if(res.success&&res.data.code){$('#ss-grid-code').text(res.data.code);SA.showNotice('Naujas kodas: '+res.data.code);}
        else SA.showNotice('Klaida generuojant kodą.','error');
      }).fail(function(){$btn.prop('disabled',false);SA.showNotice('Serverio klaida.','error');});
    });

    // ── Build grid table from currentGrid ──
    SA.buildGrid=function(){
      var periods=window.ss_periods||[];
      var $tbody=$('#ss-grid-tbody').empty();
      if(!periods.length){
        $tbody.html('<tr><td colspan="6" style="padding:24px;text-align:center;color:#6FA672">Nustatykite pamokų laiką nustatymuose.</td></tr>');
        return;
      }
      $.each(periods,function(i,p){
        var $tr=$('<tr>');
        $tr.append('<td class="ss-period-th"><div class="ss-period-info"><strong>'+p.number+'</strong><br>'+p.start+'&ndash;'+p.end+'</div></td>');
        for(var d=1;d<=5;d++){
          var entries=(currentGrid[d]&&currentGrid[d][p.id])?currentGrid[d][p.id]:[];
          var $td=$('<td>');
          var $wrap=$('<div class="ss-grid-cell-wrap" data-day="'+d+'" data-period="'+p.id+'">');

          // Filter out invalid entries and check if any are "is_free"
          var validEntries = [];
          var hasFreeSlot = false;
          if(entries.length){
            $.each(entries,function(j,e){
              if(!e||(e.lesson_id===undefined&&!e.is_free)) return;
              var isFree=e.is_free===true||e.lesson_id===0||e.lesson_id==='0';
              if(isFree) hasFreeSlot=true;
              validEntries.push({entry:e, idx:j, isFree:isFree});
            });
          }

          // Render valid entries
          $.each(validEntries,function(j,data){
            var e=data.entry, idx=data.idx, isFree=data.isFree;
            var color=isFree?'#888888':(e.lesson_color||'#4F8952');
            var subjLabel=isFree?'Nėra pamokos':(e.subject||(e.lesson_id?'Pamoka #'+e.lesson_id:''));
            var cssClass='ss-grid-subgroup has-lesson'+(isFree?' no-lesson':'');
            var $sg=$('<div class="'+cssClass+'">').css('--cell-bg',color);
            $sg.attr({'data-day':d,'data-period':p.id,'data-idx':idx});
            $sg.html(
              (isFree?'<span class="ss-cell-free-x dashicons dashicons-no-alt" style="width:14px;height:14px"></span>':'')+
              '<span class="ss-cell-subj">'+SA.esc(subjLabel)+'</span>'+
              (!isFree&&e.teacher_short?'<span class="ss-cell-tchr">'+SA.esc(e.teacher_short)+'</span>':'')+
              (e.subgroup_label?'<span class="ss-cell-sublabel">'+SA.esc(e.subgroup_label)+'</span>':'')+
              (!isFree&&e.room?'<span class="ss-cell-room">'+SA.esc(e.room)+'</span>':'')+
              '<button class="ss-sg-remove" title="Pašalinti" data-day="'+d+'" data-period="'+p.id+'" data-idx="'+idx+'">&times;</button>'
            );
            $wrap.append($sg);
          });

          // Only show "+ Pamoka" if there are no "Nėra pamokos" slots or cell is empty
          // (If user explicitly set "Nėra pamokos", they don't want another lesson there)
          if(!hasFreeSlot){
            var $add=$('<button class="ss-add-subgroup" type="button" title="Pridėti pamoką">+ Pamoka</button>')
              .attr({'data-day':d,'data-period':p.id});
            $wrap.append($add);
          }
          $td.append($wrap);
          $tr.append($td);
        }
        $tbody.append($tr);
      });
    };

    // ── Remove entry from cell ──
    $(document).on('click','.ss-sg-remove',function(e){
      e.stopPropagation();
      var d=$(this).data('day'), pid=$(this).data('period'), idx=$(this).data('idx');
      if(currentGrid[d]&&currentGrid[d][pid]){
        currentGrid[d][pid].splice(idx,1);
        if(currentGrid[d][pid].length===0) delete currentGrid[d][pid];
      }
      SA.buildGrid();
    });

    // ── Lesson picker popup ──
    SA.$picker=null;
    SA.closePicker=function(){
      if(SA.$picker){SA.$picker.remove();SA.$picker=null;}
      $(document).off('click.picker');
    };

    // Click on + button → add new lesson slot to this cell
    $(document).on('click','.ss-add-subgroup',function(e){
      e.stopPropagation();
      var d=$(this).data('day'), pid=$(this).data('period');
      SA.showPicker(d,pid,null,$(this));
    });

    // Click on existing slot → edit it
    $(document).on('click','.ss-grid-subgroup.has-lesson',function(e){
      e.stopPropagation();
      var d=$(this).data('day'),pid=$(this).data('period'),idx=$(this).data('idx');
      SA.showPicker(d,pid,idx,$(this));
    });

    SA.showPicker=function(day,pid,editIdx,$anchor){
      SA.closePicker();
      var lessons=window.ss_lessons||[];
      var isEdit=(editIdx!==null&&editIdx!==undefined);
      var currentEntry=(isEdit&&currentGrid[day]&&currentGrid[day][pid])?currentGrid[day][pid][editIdx]:null;

      // Build picker HTML
      var opts='<option value="">-- Laisva (pašalinti) --</option>'+
               '<option value="free">\u274c N\u0117ra pamokos</option>';
      $.each(lessons,function(i,l){ opts+='<option value="'+l.id+'">'+SA.esc(l.label)+'</option>'; });

      // Room (kabinetas) options — chosen from the rooms list (not free text)
      var rooms=window.ss_rooms||[];
      var roomOpts='<option value="">— Kabinetas —</option>';
      $.each(rooms,function(i,r){ roomOpts+='<option value="'+r.id+'">'+SA.esc(r.name)+'</option>'; });

      var $pick=$('<div class="ss-grid-picker-wrap">');
      $pick.html(
        '<span class="ss-grid-picker-label">'+(isEdit?'Keisti pamoką':'Pridėti pamoką')+'</span>'+
        '<select class="ss-gp-lesson">'+opts+'</select>'+
        '<input type="text" class="ss-gp-subgroup" placeholder="Pogrupis (pvz. 1 grupė)" value="">'+
        '<select class="ss-gp-room">'+roomOpts+'</select>'+
        '<button class="ss-gp-ok button button-primary button-small">Išsaugoti</button>'+
        '<button class="ss-gp-cancel button button-small">Atšaukti</button>'
      );

      // Pre-fill if editing
      if(currentEntry){
        var fillVal=(currentEntry.is_free)?'free':(currentEntry.lesson_id||'');
        $pick.find('.ss-gp-lesson').val(fillVal);
        $pick.find('.ss-gp-subgroup').val(currentEntry.subgroup_label||'');
        $pick.find('.ss-gp-room').val(currentEntry.room_id||'');
      }

      // Position
      var off=$anchor.offset();
      var top=off.top+$anchor.outerHeight()+4;
      var left=Math.min(off.left,window.innerWidth-260);
      if(top+260>window.innerHeight) top=off.top-260;
      $pick.css({top:top+'px',left:left+'px'});
      $('body').append($pick);
      SA.$picker=$pick;

      // Focus lesson select
      $pick.find('.ss-gp-lesson').focus();

      // OK button
      $pick.find('.ss-gp-ok').on('click',function(){
        var lid=$pick.find('.ss-gp-lesson').val();
        var sub=$pick.find('.ss-gp-subgroup').val().trim();
        var roomId=$pick.find('.ss-gp-room').val();
        var roomName='';
        if(roomId){
          var rooms2=window.ss_rooms||[];
          $.each(rooms2,function(i,r){ if(String(r.id)===String(roomId)){roomName=r.name;return false;} });
        }

        if(!currentGrid[day]) currentGrid[day]={};
        if(!currentGrid[day][pid]) currentGrid[day][pid]=[];

        if(!lid){
          // Empty = remove if editing
          if(isEdit){
            currentGrid[day][pid].splice(editIdx,1);
            if(currentGrid[day][pid].length===0) delete currentGrid[day][pid];
          }
        } else if(lid==='free'){
          // Free / no-lesson slot
          var entry={
            id:0,
            lesson_id:0,
            is_free:true,
            subject:'Nėra pamokos',
            teacher_short:'',
            lesson_color:'#888888',
            subgroup_label:sub,
            sort_order:isEdit?editIdx:currentGrid[day][pid].length,
            room:'',
            room_id:0
          };
          if(isEdit){ currentGrid[day][pid][editIdx]=entry; }
          else { currentGrid[day][pid].push(entry); }
        } else {
          // Find lesson details
          var lesson=null;
          $.each(lessons,function(i,l){ if(String(l.id)===String(lid)){lesson=l;return false;} });
          var subj=lesson?lesson.label.split('--')[0].replace(/\s*—\s*/,'').trim():('Pamoka #'+lid);
          // Handle " — teacher" separator
          var parts=lesson?lesson.label.split('—'):[];
          var tchr=parts.length>1?parts[1].trim():'';
          var entry={
            id:0,
            lesson_id:lid,
            is_free:false,
            subject:subj,
            teacher_short:tchr,
            lesson_color:lesson?lesson.color:'#4F8952',
            subgroup_label:sub,
            sort_order:isEdit?editIdx:currentGrid[day][pid].length,
            room:roomName,
            room_id:roomId?parseInt(roomId,10):0
          };
          if(isEdit){
            currentGrid[day][pid][editIdx]=entry;
          } else {
            currentGrid[day][pid].push(entry);
          }
        }
        SA.closePicker();
        SA.buildGrid();
      });

      // Cancel
      $pick.find('.ss-gp-cancel').on('click',function(){ SA.closePicker(); });

      // Close on outside click
      setTimeout(function(){
        $(document).on('click.picker',function(e){
          if(!$(e.target).closest('.ss-grid-picker-wrap').length) SA.closePicker();
        });
      },80);
    };

    // ── Save grid ──
    $('#ss-save-grid').on('click',function(){
      var cells=[];
      $.each(currentGrid,function(day,periods){
        $.each(periods,function(pid,entries){
          if(!$.isArray(entries)) return;
          $.each(entries,function(i,e){
            if(e&&(e.lesson_id||e.is_free)){
              cells.push({
                day:day,
                period_id:pid,
                lesson_id:e.is_free?0:e.lesson_id,
                is_free:e.is_free?1:0,
                subgroup_label:e.subgroup_label||'',
                sort_order:i,
                room:e.room||'',
                room_id:e.room_id||0
              });
            }
          });
        });
      });
      var $btn=$(this);$btn.prop('disabled',true).text('Saugoma...');
      $.post(SA.url,{action:'ss_admin_save_class_schedule',nonce:SA.nonce,
        class_id:currentClassId,cells:cells},
      function(res){
        $btn.prop('disabled',false).text('Išsaugoti');
        if(res.success){SA.showNotice(res.data.message||'Išsaugota!');$gridModal.hide();}
        else SA.showNotice('Klaida.','error');
      });
    });
  },

  /* ═══ STUDENTS ═══ */
  students:function(){
    if(!$('#ss-students-table').length && !$('#ss-modal-newuser').length) return;

    // ── Generate password ──
    $('#ss-gen-pass').on('click',function(){
      var c='abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#',pw='';
      for(var i=0;i<12;i++) pw+=c[Math.floor(Math.random()*c.length)];
      $('#ss-new-pass').val(pw);
    });

    // ── Create user ──
    $('#ss-do-create-user').on('click',function(){
      var dn=$('#ss-new-display').val().trim(),un=$('#ss-new-login').val().trim(),
          em=$('#ss-new-email').val().trim(),pw=$('#ss-new-pass').val(),rl=$('#ss-new-role').val();
      if(!dn||!un||!em){alert('Užpildykite visus privalomus laukus!');return;}
      var $btn=$(this);$btn.prop('disabled',true).text('Kuriama…');
      $.post(SA.url,{action:'ss_create_user',nonce:SA.nonce,
        display_name:dn,username:un,email:em,password:pw,role:rl},
      function(res){
        $btn.prop('disabled',false).text('Sukurti');
        if(res.success){
          SA.showNotice(res.data.message||'Sukurta!');
          $('#ss-modal-newuser').hide();
          setTimeout(function(){location.reload();},700);
        } else SA.showNotice((res.data&&res.data.message)||'Klaida.','error');
      });
    });

    // ── Assign classes (open modal) ──
    $(document).on('click','.ss-assign-btn',function(){
      var uid=$(this).data('id'),name=$(this).data('name');
      $('#ss-assign-uid').val(uid);$('#ss-assign-name').text(name);
      $.post(SA.url,{action:'ss_get_user_classes',nonce:SA.nonce,user_id:uid},function(res){
        var ids=res.success?res.data.class_ids:[];
        $('.ss-cls-check').each(function(){$(this).prop('checked',ids.indexOf(parseInt($(this).val()))>-1);});
      });
      $('#ss-modal-assign').show();
    });

    // ── Save class assignments ──
    $('#ss-save-assign').on('click',function(){
      var uid=$('#ss-assign-uid').val(),ids=[];
      $('.ss-cls-check:checked').each(function(){ids.push($(this).val());});
      var $btn=$(this);$btn.prop('disabled',true).text('Saugoma…');
      $.post(SA.url,{action:'ss_save_user_classes',nonce:SA.nonce,user_id:uid,class_ids:ids},function(res){
        $btn.prop('disabled',false).text('Išsaugoti priskyrimus');
        if(res.success){SA.showNotice('Priskyrimai išsaugoti!');$('#ss-modal-assign').hide();setTimeout(function(){location.reload();},700);}
        else SA.showNotice('Klaida.','error');
      });
    });

    // ── Single delete ──
    $(document).on('click','.ss-delete-student',function(){
      var name=$(this).data('name'),id=$(this).data('id');
      if(!confirm('Ištrinti mokinį "'+name+'"? Bus panaikinta jo paskyra!'))return;
      $.post(SA.url,{action:'ss_bulk_delete_students',nonce:SA.nonce,ids:[id]},function(res){
        if(res.success){$('#ss-student-row-'+id).fadeOut(250,function(){$(this).remove();});SA.showNotice('Mokinys ištrintas!');}
        else SA.showNotice((res.data&&res.data.message)||'Klaida.','error');
      });
    });
  },

  /* ═══ IMPORT ═══ */
  importFlow:function(){
    var $s1=$('#ss-step1'),$s2=$('#ss-step2');
    if(!$s1.length)return;
    var $fi=$('#ss-html-file'),$pb=$('#ss-parse-btn'),$dz=$('#ss-drop-zone');
    var importData=null;

    // File input is absolutely positioned over the whole zone — click works natively
    // But we also handle keyboard Enter/Space for accessibility
    $dz.on('keydown',function(e){
      if(e.key==='Enter'||e.key===' '){e.preventDefault();$fi.click();}
    });

    function setFile(f){
      if(!f) return;
      $('#ss-upload-idle').hide();
      $('#ss-file-name').text(f.name);
      $('#ss-upload-ready').show();
      $dz.addClass('has-file');
      $pb.prop('disabled',false);
    }

    $fi.on('change',function(){
      if(this.files.length) setFile(this.files[0]);
    });
    $dz.on('dragover',function(e){e.preventDefault();$(this).addClass('drag-over');});
    $dz.on('dragleave',function(){$(this).removeClass('drag-over');});
    $dz.on('drop',function(e){
      e.preventDefault();$(this).removeClass('drag-over');
      var f=e.originalEvent.dataTransfer.files[0];
      if(f){
        try{var dt=new DataTransfer();dt.items.add(f);$fi[0].files=dt.files;}catch(ex){}
        setFile(f);
      }
    });

    $pb.on('click',function(){
      var f=$fi[0].files[0];if(!f){alert('Pasirinkite failą!');return;}
      $('#ss-upload-err').hide();$('#ss-parse-loading').show();$pb.prop('disabled',true);
      var reader=new FileReader();
      reader.onload=function(ev){
        // Send raw bytes as base64 – avoids multipart file-upload WAF blocks
        var bytes=new Uint8Array(ev.target.result),bin='';
        for(var i=0;i<bytes.byteLength;i++) bin+=String.fromCharCode(bytes[i]);
        $.ajax({url:SA.url,method:'POST',data:{action:'ss_parse_import',nonce:SA.nonce,html_base64:btoa(bin)},
          success:function(res){
            $('#ss-parse-loading').hide();$pb.prop('disabled',false);
            if(!res.success){$('#ss-upload-err').text((res.data&&res.data.message)||'Klaida.').show();return;}
            importData=res.data;window._importData=importData;
            SA.renderPreview(importData);
            $s1.hide();$s2.show();
          },error:function(){
            $('#ss-parse-loading').hide();$pb.prop('disabled',false);
            $('#ss-upload-err').text('Serverio klaida.').show();
          }
        });
      };
      reader.onerror=function(){
        $('#ss-parse-loading').hide();$pb.prop('disabled',false);
        $('#ss-upload-err').text('Nepavyko nuskaityti failo.').show();
      };
      reader.readAsArrayBuffer(f);
    });

    $('#ss-back-btn').on('click',function(){$s2.hide();$s1.show();importData=null;});

    $('#ss-commit-btn').on('click',function(){
      if(!importData)return;
      importData.class_name=$('#ss-imp-class').val().trim()||importData.class_name;
      var $btn=$(this);$btn.prop('disabled',true).text('Importuojama...');
      $('#ss-import-err').hide();$('#ss-import-ok').hide();
      $.post(SA.url,{action:'ss_commit_import',nonce:SA.nonce,import_data:JSON.stringify(importData)},function(res){
        $btn.prop('disabled',false).text('Patvirtinti importa');
        if(res.success){
          $('#ss-import-ok').text(res.data.message).show();
          setTimeout(function(){window.location.href=SA.url.replace('admin-ajax.php','admin.php?page=ss-classes');},1500);
        } else {
          $('#ss-import-err').text((res.data&&res.data.message)||'Klaida.').show();
        }
      });
    });
  },

  renderPreview:function(d){
    $('#ss-imp-class').val(d.class_name||'');
    var $tl=$('#ss-new-teachers-list').empty();
    $('#ss-new-teacher-count').text('('+d.new_teachers.length+')');
    if(!d.new_teachers.length){$('#ss-new-teachers-sec').hide();}
    else{
      $('#ss-new-teachers-sec').show();
      $.each(d.new_teachers,function(i,n){
        $tl.append('<div class="ss-import-item"><span class="dashicons dashicons-admin-users" style="font-size:15px;width:15px;height:15px;vertical-align:text-bottom"></span><span>'+SA.esc(n)+'</span></div>');
      });
    }
    var $ll=$('#ss-new-lessons-list').empty();
    $('#ss-new-lessons-count').text('('+d.new_lessons.length+')');
    if(!d.new_lessons.length){$('#ss-new-lessons-sec').hide();}
    else{
      $('#ss-new-lessons-sec').show();
      $.each(d.new_lessons,function(i,key){
        var parts=key.split('|||'),subj=parts[0],tchr=parts[1]||'';
        $ll.append('<div class="ss-import-item"><span class="dashicons dashicons-book" style="font-size:15px;width:15px;height:15px;vertical-align:text-bottom"></span><span>'+SA.esc(subj)+'</span><span style="color:#6FA672;margin-left:8px;font-size:12px">'+SA.esc(tchr)+'</span></div>');
      });
    }
    // Schedule preview table
    var days=['','Pirmadienis','Antradienis','Trečiadienis','Ketvirtadienis','Penktadienis'];
    var periodMap={};
    $.each(d.cells,function(i,c){if(!periodMap[c.period])periodMap[c.period]=true;});
    var periods=Object.keys(periodMap).map(Number).sort(function(a,b){return a-b;});
    var $thead=$('#ss-preview-thead').empty(),$tbody=$('#ss-preview-tbody').empty();
    var $hr=$('<tr><th>#</th>');
    for(var dd=1;dd<=5;dd++) $hr.append('<th>'+days[dd]+'</th>');
    $thead.append($hr);
    var cm={};
    $.each(d.cells,function(i,c){
      var k=c.day+'_'+c.period;if(!cm[k])cm[k]=[];
      $.each(c.entries,function(j,e){cm[k].push({entry:e,ci:i,ei:j});});
    });
    $.each(periods,function(pi,pn){
      var $tr=$('<tr><td><strong>'+pn+'</strong></td>');
      for(var dd=1;dd<=5;dd++){
        var k=dd+'_'+pn,$td=$('<td>');
        if(cm[k]&&cm[k].length){
          var $cell=$('<div class="ss-preview-cell">');
          $.each(cm[k],function(i,item){
            var e=item.entry;
            var $en=$('<div class="ss-preview-entry">').html(
              '<div class="ss-entry-subj">'+SA.esc(e.subject)+'</div>'+
              (e.teachers&&e.teachers.length?'<div class="ss-entry-tchr">'+SA.esc(e.teachers.join(', '))+'</div>':'')+
              (e.subgroup_label?'<div class="ss-entry-sub">'+SA.esc(e.subgroup_label)+'</div>':'')+
              '<button class="ss-entry-del" data-ci="'+item.ci+'" data-ei="'+item.ei+'"><span class="dashicons dashicons-no-alt" style="font-size:14px;width:14px;height:14px;vertical-align:text-bottom"></span></button>'
            );
            $cell.append($en);
          });
          $td.append($cell);
        } else {
          $td.text('–').css('color','#EBEBEB');
        }
        $tr.append($td);
      }
      $tbody.append($tr);
    });
    // Delete entry from preview
    $(document).off('click.previewdel').on('click.previewdel','.ss-entry-del',function(e){
      e.stopPropagation();
      var ci=$(this).data('ci'),ei=$(this).data('ei');
      if(window._importData&&window._importData.cells[ci]){
        window._importData.cells[ci].entries.splice(ei,1);
        SA.renderPreview(window._importData);
      }
    });
  },

  esc:function(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
,

  /* ══ BULK ACTIONS + SEARCH ══ */
  bulk:function(){
    var cfgs=[
      {e:'teachers',am:{delete:{a:'ss_bulk_delete_teachers',l:'mokytojų',c:'Ištrinti pasirinktus mokytojus?'}}},
      {e:'rooms',   am:{delete:{a:'ss_bulk_delete_rooms',   l:'kabinetų',c:'Ištrinti pasirinktus kabinetus?'}}},
      {e:'lessons', am:{delete:{a:'ss_bulk_delete_lessons', l:'pamokų',  c:'Ištrinti pasirinktus pamokas?'}}},
      {e:'classes', am:{
        toggle_imp:{a:'ss_bulk_toggle_imp',    l:'',             c:'Perjungti IMP žymą pasirinktiems tvarkaraščiams?'},
        delete:    {a:'ss_bulk_delete_classes',l:'tvarkaraščių', c:'Ištrinti pasirinktus tvarkaraščius? Bus ištrinti ir pamokų duomenys!'}
      }},
      {e:'students',am:{delete:{a:'ss_bulk_delete_students',l:'mokinių',c:'Ištrinti pasirinktus mokinius? Bus panaikintos jų paskyros!'}}}
    ];
    $.each(cfgs,function(_,cfg){
      var e=cfg.e, $tbl=$('#ss-'+e+'-table');
      if(!$tbl.length) return;
      /* Select all */
      $(document).on('change','#ss-'+e+'-select-all,#ss-'+e+'-th-all',function(){
        var v=$(this).prop('checked');
        $tbl.find('tbody tr:not(.ss-hidden) .ss-row-cb').prop('checked',v).closest('tr').toggleClass('ss-row-selected',v);
        $('#ss-'+e+'-select-all,#ss-'+e+'-th-all').prop('checked',v).prop('indeterminate',false);
        SA.bulkUpd(e,$tbl);
      });
      /* Row checkbox */
      $tbl.on('change','.ss-row-cb',function(){
        $(this).closest('tr').toggleClass('ss-row-selected',$(this).is(':checked'));
        var vis=$tbl.find('tbody tr:not(.ss-hidden)');
        var sel=vis.find('.ss-row-cb:checked').length,tot=vis.find('.ss-row-cb').length;
        $('#ss-'+e+'-select-all,#ss-'+e+'-th-all').prop('checked',tot>0&&sel===tot).prop('indeterminate',sel>0&&sel<tot);
        SA.bulkUpd(e,$tbl);
      });
      /* Search */
      $('#ss-'+e+'-search').on('input',function(){
        var q=$(this).val().toLowerCase().trim();
        $tbl.find('tbody tr').each(function(){
          var ok=!q||(($(this).data('search')||'').indexOf(q)!==-1);
          $(this).toggleClass('ss-hidden',!ok);
          if(!ok) $(this).find('.ss-row-cb').prop('checked',false).closest('tr').removeClass('ss-row-selected');
        });
        $('#ss-'+e+'-select-all,#ss-'+e+'-th-all').prop('checked',false).prop('indeterminate',false);
        SA.bulkUpd(e,$tbl);
      });
      /* Apply */
      $(document).on('click','.ss-bulk-apply[data-entity="'+e+'"]',function(){
        var act=$('#ss-'+e+'-action').val();
        if(!act){alert('Pasirinkite veiksmą!');return;}
        var ac=cfg.am[act];if(!ac){alert('Nežinomas veiksmas.');return;}
        var ids=[];
        $tbl.find('.ss-row-cb:checked').each(function(){ids.push($(this).val());});
        if(!ids.length){alert('Nepasirinkta nė viena eilutė!');return;}
        if(!confirm(ac.c+'\n\nPasirinkta: '+ids.length+' vnt.')) return;
        var $btn=$(this);$btn.prop('disabled',true).text('Vykdoma\u2026');
        $.post(SA.url,{action:ac.a,nonce:SA.nonce,ids:ids},function(res){
          $btn.prop('disabled',false).text('Vykdyti');
          if(!res.success){SA.showNotice((res.data&&res.data.message)||'Klaida.','error');return;}
          if(act==='delete'){
            $.each(ids,function(_,id){
              $tbl.find('#ss-teacher-row-'+id).add('#ss-room-row-'+id).add('#ss-lesson-row-'+id).add('#ss-cls-row-'+id).add('#ss-student-row-'+id).fadeOut(220,function(){$(this).remove();});
            });
            SA.showNotice('Ištrinta: '+ids.length+(ac.l?' '+ac.l:'')+'.');
          } else if(act==='toggle_imp'){
            $.each(res.data.results||{},function(cid,v){
              $('#ss-cls-row-'+cid).attr('data-imp',v?'1':'0');
              $('#ss-imp-cell-'+cid).html(v?'<span class="ss-imp-pill">Taip</span>':'<span class="ss-no-imp">Ne</span>');
              var $b=$('#ss-imp-badge-'+cid);
              $b.replaceWith(v?'<span class="ss-imp-pill" id="ss-imp-badge-'+cid+'">IMP</span>':'<span id="ss-imp-badge-'+cid+'"></span>');
              $('#ss-cls-row-'+cid+' .ss-edit-class').attr('data-is-imp',v?'1':'0');
            });
            SA.showNotice('IMP žyma perjungta '+ids.length+' tvarkaraščiams.');
          }
          $tbl.find('.ss-row-cb').prop('checked',false).closest('tr').removeClass('ss-row-selected');
          $('#ss-'+e+'-select-all,#ss-'+e+'-th-all').prop('checked',false).prop('indeterminate',false);
          $('#ss-'+e+'-action').val('');
          SA.bulkUpd(e,$tbl);
        }).fail(function(){$btn.prop('disabled',false).text('Vykdyti');SA.showNotice('Serverio klaida.','error');});
      });
    });
  },

  bulkUpd:function(e,$t){
    var n=($t||$('#ss-'+e+'-table')).find('.ss-row-cb:checked').length;
    $('#ss-'+e+'-badge').toggle(n>0);$('#ss-'+e+'-sel-n').text(n);$('#ss-'+e+'-bulk-right').toggle(n>0);
  }
};

$(document).ready(function(){SA.init();});
})(jQuery);

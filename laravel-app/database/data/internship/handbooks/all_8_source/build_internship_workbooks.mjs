import fs from 'node:fs/promises';
import path from 'node:path';
import { Workbook, SpreadsheetFile } from '@oai/artifact-tool';

const base='/workspace/scratch/39f6c37332d1';
const inputDir=path.join(base,'Beyond_Internship_180_Day_Programs');
const outputDir=path.join(base,'outputs','internship_workbooks');
const previewDir=path.join('/tmp','beyond_internship_workbook_previews');
await fs.mkdir(outputDir,{recursive:true}); await fs.mkdir(previewDir,{recursive:true});

const colors={navy:'#12344D',blue:'#176B87',teal:'#2A9D8F',light:'#EAF5F7',gray:'#667085',pale:'#F5F7FA',gold:'#E9C46A',white:'#FFFFFF',green:'#DDF4E4'};
const keys=['CyberSecurity','DataScience','SoftwareDev','Intercom','Lighting','LiveSound','Networking','ScreensVideo'];
const display={CyberSecurity:'Cyber Security',DataScience:'Data Science',SoftwareDev:'Software Development',Intercom:'Intercom Systems',Lighting:'Lighting Engineering',LiveSound:'Live Sound Engineering',Networking:'Networking',ScreensVideo:'Screens and Video'};

function styleTitle(sheet,range){const r=sheet.getRange(range);r.format={fill:colors.navy,font:{bold:true,color:colors.white,size:18},verticalAlignment:'center'};r.format.rowHeight=32;}
function styleHeader(range){range.format={fill:colors.blue,font:{bold:true,color:colors.white},verticalAlignment:'center',wrapText:true,borders:{preset:'outside',style:'thin',color:'#9FB5C2'}};range.format.rowHeight=28;}
function styleBody(range){range.format={font:{color:'#172B3A'},verticalAlignment:'top',wrapText:true,borders:{insideHorizontal:{style:'thin',color:'#D8E0E5'}}};}
function setWidths(sheet,map){for(const [col,width] of Object.entries(map))sheet.getRange(`${col}:${col}`).format.columnWidth=width;}

function addOverview(wb,program,key){
 const s=wb.worksheets.add('Overview');s.showGridLines=false;
 s.mergeCells('A1:F2');s.getRange('A1').values=[[program.program_title]];styleTitle(s,'A1:F2');
 s.getRange('A4:B10').values=[['Metric','Value'],['Program',display[key]],['Total days',null],['Daily hours',program.daily_duration_hours],['Total guided hours',null],['Phases',null],['Version',program.program_version]];
 styleHeader(s.getRange('A4:B4'));styleBody(s.getRange('A5:B10'));
 s.getRange('B6').formulas=[["=COUNTA('180 Days'!A5:A184)"]];
 s.getRange('B8').formulas=[["=SUM('180 Days'!H5:H184)"]];
 s.getRange('B9').formulas=[["=COUNTA(Phases!A5:A22)"]];
 s.getRange('B6:B9').format.numberFormat='#,##0';
 s.getRange('D4:F4').merge();s.getRange('D4').values=[['Program Safety Rule']];styleHeader(s.getRange('D4:F4'));
 s.getRange('D5:F8').merge();s.getRange('D5').values=[[program.global_rules.join('\n• ')]];s.getRange('D5:F8').format={fill:'#FDECEC',font:{color:'#7A271A'},wrapText:true,verticalAlignment:'top'};
 s.getRange('D10:F10').merge();s.getRange('D10').values=[['How to use this workbook']];styleHeader(s.getRange('D10:F10'));
 s.getRange('D11:F15').merge();s.getRange('D11').values=[["Use the Phases sheet for curriculum sequencing and the 180 Days sheet for import review, scheduling, status tracking, tools, objectives, prerequisites, and submissions. Status is editable. All practical details remain in the JSON and Markdown files included in the package."]];s.getRange('D11:F15').format={fill:colors.light,wrapText:true,verticalAlignment:'top'};
 setWidths(s,{A:24,B:24,C:4,D:24,E:24,F:24});s.freezePanes.freezeRows(2);
 return s;
}

function addPhases(wb,program){
 const s=wb.worksheets.add('Phases');s.showGridLines=false;
 s.mergeCells('A1:E2');s.getRange('A1').values=[['18-Phase Curriculum Map']];styleTitle(s,'A1:E2');
 const rows=[['Phase #','Phase Name','Days','Start Day','End Day'],...program.phases.map(p=>{const [a,b]=p.days.split('-').map(Number);return[p.phase_number,p.name,p.days,a,b]})];
 s.getRange(`A4:E${rows.length+3}`).values=rows;styleHeader(s.getRange('A4:E4'));styleBody(s.getRange(`A5:E${rows.length+3}`));
 s.getRange(`A5:A${rows.length+3}`).format.numberFormat='0';s.getRange(`D5:E${rows.length+3}`).format.numberFormat='0';
 const t=s.tables.add(`A4:E${rows.length+3}`,true,'PhasesTable');t.style='TableStyleMedium2';
 setWidths(s,{A:11,B:58,C:14,D:12,E:12});s.freezePanes.freezeRows(4);
 return s;
}

function addDays(wb,program,key){
 const s=wb.worksheets.add('180 Days');s.showGridLines=false;
 s.mergeCells('A1:N2');s.getRange('A1').values=[[`${display[key]} — Complete 180-Day Index`]];styleTitle(s,'A1:N2');
 const head=['Day','Code','Phase #','Phase','Mode','Topic','Difficulty','Hours','Tools','Objective','Prerequisite','Required Submission','Status','Supervisor Notes'];
 const rows=[head,...program.days.map(d=>[d.day,d.day_code,d.phase_number,d.phase,d.mode,d.topic,d.difficulty,d.estimated_time_hours,d.tools.join(' | '),d.objective,d.prerequisite,d.required_submission,'Not Started',''])];
 s.getRange(`A4:N${rows.length+3}`).values=rows;styleHeader(s.getRange('A4:N4'));styleBody(s.getRange(`A5:N${rows.length+3}`));
 s.getRange(`A5:A${rows.length+3}`).format.numberFormat='0';s.getRange(`C5:C${rows.length+3}`).format.numberFormat='0';s.getRange(`H5:H${rows.length+3}`).format.numberFormat='0';
 s.getRange(`M5:M${rows.length+3}`).dataValidation={rule:{type:'list',values:['Not Started','In Progress','Completed','Needs Review','Blocked']}};
 s.getRange(`M5:M${rows.length+3}`).conditionalFormats.add('containsText',{text:'Completed',format:{fill:colors.green,font:{color:'#196B2C',bold:true}}});
 s.getRange(`M5:M${rows.length+3}`).conditionalFormats.add('containsText',{text:'Blocked',format:{fill:'#FDECEC',font:{color:'#B42318',bold:true}}});
 s.getRange(`M5:M${rows.length+3}`).conditionalFormats.add('containsText',{text:'In Progress',format:{fill:'#FFF4D6',font:{color:'#7A5A00',bold:true}}});
 const t=s.tables.add(`A4:N${rows.length+3}`,true,`${key}DaysTable`);t.style='TableStyleMedium2';
 setWidths(s,{A:7,B:13,C:9,D:34,E:23,F:45,G:20,H:8,I:42,J:55,K:48,L:50,M:17,N:36});
 s.getRange(`A5:N${rows.length+3}`).format.rowHeight=54;s.freezePanes.freezeRows(4);s.freezePanes.freezeColumns(3);
 return s;
}

async function exportProgram(key){
 const program=JSON.parse(await fs.readFile(path.join(inputDir,`Beyond_${key}_Internship_180_Day_Program.json`),'utf8'));
 const wb=Workbook.create();addOverview(wb,program,key);addPhases(wb,program);addDays(wb,program,key);
 const inspect=await wb.inspect({kind:'table',sheetId:'180 Days',range:'A1:N12',include:'values,formulas',tableMaxRows:12,tableMaxCols:14,maxChars:5000});
 await fs.writeFile(path.join(previewDir,`${key}_inspect.ndjson`),inspect.ndjson??'');
 const err=await wb.inspect({kind:'match',searchTerm:'#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A',options:{useRegex:true,maxResults:100},summary:'formula error scan'});
 await fs.writeFile(path.join(previewDir,`${key}_errors.ndjson`),err.ndjson??'');
 for(const [sheet,range] of [['Overview','A1:F15'],['Phases','A1:E22'],['180 Days','A1:N18']]){
  const blob=await wb.render({sheetName:sheet,range,scale:1,format:'png'});await fs.writeFile(path.join(previewDir,`${key}_${sheet.replaceAll(' ','_')}.png`),new Uint8Array(await blob.arrayBuffer()));
 }
 const out=path.join(outputDir,`Beyond_${key}_Internship_180_Day_Workbook.xlsx`);const file=await SpreadsheetFile.exportXlsx(wb);await file.save(out);return {key,out,program};
}

const built=[];for(const key of keys)built.push(await exportProgram(key));

// Master workbook: one overview plus one compact 180-day sheet per program.
const master=Workbook.create();const ov=master.worksheets.add('Portfolio Overview');ov.showGridLines=false;
ov.mergeCells('A1:G2');ov.getRange('A1').values=[['Beyond Enterprise — Eight Internship Programs']];styleTitle(ov,'A1:G2');
const summary=[['Program','Days','Daily Hours','Guided Hours','Phases','JSON Import','Workbook']];
for(const b of built)summary.push([display[b.key],b.program.total_days,b.program.daily_duration_hours,b.program.total_guided_hours,b.program.phases.length,`Beyond_${b.key}_Internship_180_Day_Program.json`,path.basename(b.out)]);
summary.push(['TOTAL',null,'',null,null,'','']);ov.getRange(`A4:G${summary.length+3}`).values=summary;styleHeader(ov.getRange('A4:G4'));styleBody(ov.getRange(`A5:G${summary.length+3}`));
const totalRow=summary.length+3;ov.getRange(`B${totalRow}`).formulas=[[`=SUM(B5:B12)`]];ov.getRange(`D${totalRow}`).formulas=[[`=SUM(D5:D12)`]];ov.getRange(`E${totalRow}`).formulas=[[`=SUM(E5:E12)`]];ov.getRange(`A${totalRow}:G${totalRow}`).format={fill:colors.teal,font:{bold:true,color:colors.white}};
setWidths(ov,{A:28,B:10,C:12,D:16,E:10,F:42,G:45});ov.freezePanes.freezeRows(4);ov.tables.add(`A4:G${totalRow}`,true,'PortfolioTable').style='TableStyleMedium2';
for(const b of built){const s=master.worksheets.add(b.key.substring(0,31));s.showGridLines=false;s.mergeCells('A1:J2');s.getRange('A1').values=[[`${display[b.key]} — 180 Days`]];styleTitle(s,'A1:J2');const rows=[['Day','Phase #','Phase','Mode','Topic','Difficulty','Hours','Tools','Objective','Status'],...b.program.days.map(d=>[d.day,d.phase_number,d.phase,d.mode,d.topic,d.difficulty,d.estimated_time_hours,d.tools.join(' | '),d.objective,'Not Started'])];s.getRange(`A4:J${rows.length+3}`).values=rows;styleHeader(s.getRange('A4:J4'));styleBody(s.getRange(`A5:J${rows.length+3}`));s.getRange(`J5:J${rows.length+3}`).dataValidation={rule:{type:'list',values:['Not Started','In Progress','Completed','Needs Review','Blocked']}};s.tables.add(`A4:J${rows.length+3}`,true,`${b.key}MasterTable`).style='TableStyleMedium2';setWidths(s,{A:7,B:9,C:34,D:22,E:44,F:20,G:8,H:40,I:58,J:17});s.getRange(`A5:J${rows.length+3}`).format.rowHeight=50;s.freezePanes.freezeRows(4);s.freezePanes.freezeColumns(2);}
const masterErr=await master.inspect({kind:'match',searchTerm:'#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A',options:{useRegex:true,maxResults:200},summary:'master formula error scan'});await fs.writeFile(path.join(previewDir,'Master_errors.ndjson'),masterErr.ndjson??'');
for(const sheet of ['Portfolio Overview',...keys]){const range=sheet==='Portfolio Overview'?'A1:G14':'A1:J18';const blob=await master.render({sheetName:sheet,range,scale:1,format:'png'});await fs.writeFile(path.join(previewDir,`Master_${sheet.replaceAll(' ','_')}.png`),new Uint8Array(await blob.arrayBuffer()));}
const masterPath=path.join(outputDir,'Beyond_All_8_Internship_180_Day_Master_Workbook.xlsx');const masterFile=await SpreadsheetFile.exportXlsx(master);await masterFile.save(masterPath);
console.log(JSON.stringify({outputDir,previewDir,workbooks:[...built.map(x=>x.out),masterPath]}));

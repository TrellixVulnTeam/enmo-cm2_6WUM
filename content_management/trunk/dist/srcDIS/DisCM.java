package discm;

import java.io.BufferedReader;
import java.io.IOException;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.io.OutputStreamWriter;
import java.io.Writer;
import java.lang.reflect.InvocationTargetException;
import java.net.HttpURLConnection;
import java.net.URL;
import java.security.PrivilegedActionException;
import java.util.Hashtable;
import java.util.Iterator;
import java.util.Timer;
import java.util.TimerTask;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;
import java.util.logging.Level;
import java.util.logging.Logger;
import javax.swing.JApplet;
import javax.xml.parsers.DocumentBuilder;
import javax.xml.parsers.DocumentBuilderFactory;
import javax.xml.parsers.ParserConfigurationException;
import netscape.javascript.JSException;
import org.w3c.dom.Document;
import org.w3c.dom.NodeList;
import org.xml.sax.SAXException;
import netscape.javascript.JSObject;

import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;


/**
 *
 * @author Laurent Giovannoni
 */
public class DisCM extends JApplet {
    //INIT PARAMETERS
    protected String url;
    protected String objectType;
    protected String objectTable;
    protected String objectId;
    protected String cookie;
    protected String userLocalDirTmp;
    protected String userMaarch;
    protected String userMaarchPwd;
    protected String psExecMode;
    
    protected String messageStatus;
    
    Hashtable app = new Hashtable();
    Hashtable messageResult = new Hashtable();
    
    //XML PARAMETERS
    protected String status;
    protected String appPath;
    protected String appPath_convert;
    protected String fileContent;
    protected String fileContentVbs;
    protected String vbsPath;
    protected String fileContentExe;
    protected String useExeConvert;
    protected String fileExtension;
    protected String error;
    protected String endMessage;
    protected String os;
    
    protected String fileContentTosend;
    protected String pdfContentTosend;
    
    public myLogger logger;
    
    public fileManager fM;
    public String fileToEdit;
    
    public void init() throws JSException
    {
        System.out.println("----------BEGIN PARAMETERS----------");
        this.url = this.getParameter("url");
        this.objectType = this.getParameter("objectType");
        this.objectTable = this.getParameter("objectTable");
        this.objectId = this.getParameter("objectId");
        this.cookie = this.getParameter("cookie");
        this.userMaarch = this.getParameter("userMaarch");
        this.userMaarchPwd = this.getParameter("userMaarchPwd");
        this.psExecMode = this.getParameter("psExecMode");
        
        System.out.println("URL : " + this.url);
        System.out.println("OBJECT TYPE : " + this.objectType);
        System.out.println("OBJECT TABLE : " + this.objectTable);
        System.out.println("OBJECT ID : " + this.objectId);
        System.out.println("COOKIE : " + this.cookie);
        System.out.println("USER MAARCH : " + this.userMaarch);
        System.out.println("PSEXEC MODE : " + this.psExecMode);
        
        System.out.println("----------END PARAMETERS----------");
        try {
            this.editObject();
            this.destroy();
            this.stop();
            System.exit(0);
        } catch (Exception ex) {
            Logger.getLogger(DisCM.class.getName()).log(Level.SEVERE, null, ex);
        }
    }
    
    
    public void createPDF(String docxFile, String directory, boolean isUnix) {
		try {
			
			
			String cmd = "";
			if (docxFile.contains(".odt") || docxFile.contains(".ods") || docxFile.contains(".ODT") || docxFile.contains(".ODS")) {
				String convertProgram;
				convertProgram = this.fM.findPathProgramInRegistry("soffice.exe");
	            
				cmd = convertProgram+" -env:UserInstallation=$SYSUSERCONFIG --headless --convert-to pdf --outdir \""+this.userLocalDirTmp.substring(0,this.userLocalDirTmp.length()-1)+"\" \""+docxFile+"\" \r\n";
			}
			else{
				if (this.useExeConvert.equals("false"))
					cmd = "cmd /C cscript \""+this.vbsPath+"\" \""+docxFile+"\" /nologo \r\n";
				else{
					
					StringBuffer buffer = new StringBuffer(docxFile);
					buffer.replace(buffer.lastIndexOf("."),	buffer.length(), ".pdf");
					String pdfOut = buffer.toString();
					
					cmd = "cmd /C \""+this.userLocalDirTmp+"Word2Pdf.exe\" \""+docxFile+"\" \""+pdfOut+"\" \r\n";
				}
			}
			
			
			
            
            this.logger.log("EXEC PATH : " +cmd, Level.INFO);
            fileManager fM = new fileManager();
                      
                        Process proc_vbs;
            if (isUnix){
            	cmd = "cscript \""+this.vbsPath+"\" \""+docxFile+"\" /nologo \r\n";
            	final Writer outBat;
    			outBat = new OutputStreamWriter(new FileOutputStream(this.appPath_convert), "CP850");
    			this.logger.log("--- cmd bat  --- "+cmd, Level.INFO);
    			outBat.write(cmd);
    			outBat.write("exit \r\n");
    			outBat.close();
    			
    			File myFileBat = new File(this.appPath_convert);
    			myFileBat.setReadable(true, false);
    			myFileBat.setWritable(true, false);
    			myFileBat.setExecutable(true, false);
    			
    			//String cmd2 = "start /B /MIN "+this.appPath_convert+" \r\n";
    			String cmd2 = "start /WAIT /MIN "+this.appPath_convert+" \r\n";
    			final Writer outBat2 = new OutputStreamWriter(new FileOutputStream(this.appPath), "CP850");
    			outBat2.write(cmd2);
    			outBat2.write("exit \r\n");
    			outBat2.close();
    			
    			File myFileBat2 = new File(this.appPath);
    			myFileBat2.setReadable(true, false);
    			myFileBat2.setWritable(true, false);
    			myFileBat2.setExecutable(true, false);
            	
    			final String exec_vbs = "\""+this.appPath+"\"";
            	proc_vbs = fM.launchApp(exec_vbs);
            }
            else {
            	proc_vbs = fM.launchApp(cmd);
            }
            proc_vbs.waitFor();
            
		} catch (Throwable e) {
			this.logger.log("--- Erreur dans la conversion --- ", Level.INFO);
			e.printStackTrace();
		}
	}
    
    public void test(String[] args)
    {
        System.out.println("----------TESTS----------");
        System.out.println("----------BEGIN PARAMETERS----------");
        this.url = args[0];
        this.objectType = args[1];
        this.objectTable = args[2];
        this.objectId = args[3];
        this.cookie = args[4];
        this.userMaarch = args[5];
        this.userMaarchPwd = args[6];
        this.psExecMode = args[7];
        
        System.out.println("URL : " + this.url);
        System.out.println("OBJECT TYPE : " + this.objectType);
        System.out.println("OBJECT TABLE : " + this.objectTable);
        System.out.println("OBJECT ID : " + this.objectId);
        System.out.println("COOKIE : " + this.cookie);
        System.out.println("USER MAARCH : " + this.userMaarch);
        System.out.println("USER MAARCHPWD : " + this.userMaarchPwd);
        System.out.println("PSEXEC MODE : " + this.psExecMode);
        
        System.out.println("----------END PARAMETERS----------");
        try {
            this.editObject();
        } catch (Exception ex) {
            Logger.getLogger(DisCM.class.getName()).log(Level.SEVERE, null, ex);
        }
    }
    
    public void parse_xml(InputStream flux_xml) throws SAXException, IOException, ParserConfigurationException
    {
        this.logger.log("----------BEGIN PARSE XML----------", Level.INFO);
        DocumentBuilder builder = DocumentBuilderFactory.newInstance().newDocumentBuilder();
        Document doc = builder.parse(flux_xml);
        this.messageResult.clear();
        NodeList level_one_list = doc.getChildNodes();
        for (Integer i=0; i < level_one_list.getLength(); i++) {
            NodeList level_two_list = level_one_list.item(i).getChildNodes();
            if ("SUCCESS".equals(level_one_list.item(i).getNodeName())) {
                for(Integer j=0; j < level_one_list.item(i).getChildNodes().getLength(); j++ ) {
                    this.messageResult.put(level_two_list.item(j).getNodeName(),level_two_list.item(j).getTextContent());
                }
                this.messageStatus = "SUCCESS";
            } else if ("ERROR".equals(level_one_list.item(i).getNodeName()) ) {
                for(Integer j=0; j < level_one_list.item(i).getChildNodes().getLength(); j++ ) {
                    this.messageResult.put(level_two_list.item(j).getNodeName(),level_two_list.item(j).getTextContent());
                }
                this.messageStatus = "ERROR";
            }
        }
        this.logger.log("----------END PARSE XML----------", Level.INFO);
    }
    
    public void processReturn(Hashtable result) {
        Iterator itValue = result.values().iterator(); 
        Iterator itKey = result.keySet().iterator();
        while(itValue.hasNext()) {
            String value = (String)itValue.next();
            String key = (String)itKey.next();
            this.logger.log(key + " : " + value, Level.INFO);
            if ("STATUS".equals(key)) {
                this.status = value;
            }
            if ("OBJECT_TYPE".equals(key)) {
                this.objectType = value;
            }
            if ("OBJECT_TABLE".equals(key)) {
                this.objectTable = value;
            }
            if ("OBJECT_ID".equals(key)) {
                this.objectId = value;
            }
            if ("COOKIE".equals(key)) {
                this.cookie = value;
            }
            if ("APP_PATH".equals(key)) {
                //this.appPath = value;
            }
            if ("FILE_CONTENT".equals(key)) {
                this.fileContent = value;
            }
            if ("FILE_CONTENT_VBS".equals(key)) {
                this.fileContentVbs = value;
            }
            if ("VBS_PATH".equals(key)) {
                this.vbsPath = value;
            }
            if ("FILE_CONTENT_EXE".equals(key)) {
                this.fileContentExe = value;
            }
            if ("USE_EXE_CONVERT".equals(key)) {
                this.useExeConvert = value;
            }
            if ("FILE_EXTENSION".equals(key)) {
                this.fileExtension = value;
            }
            if ("ERROR".equals(key)) {
                this.error = value;
            }
            if ("END_MESSAGE".equals(key)) {
                this.endMessage = value;
            }
        }
        //send message error to Maarch if necessary
        if (!this.error.isEmpty()) {
            this.sendJsMessage(this.error.toString());
        }
    }
    
    /**
     * @param args the command line arguments
     */
    public static void main(String[] args) {
        try{
            System.out.println(args[0]);
            System.out.println(args[1]);
            System.out.println(args[2]);
            System.out.println(args[3]);
            System.out.println(args[4]);
            DisCM disCM = new DisCM();
            disCM.test(args);
        }
       catch(Exception e) {
           String exMessage = e.toString();
           System.out.println(exMessage);
       }
    }
    
    public String editObject() throws Exception, InterruptedException, JSException {
        System.out.println("----------BEGIN EDIT OBJECT----------");
        System.out.println("----------BEGIN LOCAL DIR TMP IF NOT EXISTS----------");
        String os = System.getProperty("os.name").toLowerCase();
        boolean isUnix = os.indexOf("nix") >= 0 || os.indexOf("nux") >= 0;
        boolean isWindows = os.indexOf("win") >= 0;
        boolean isMac = os.indexOf("mac") >= 0;
        this.userLocalDirTmp = System.getProperty("user.home");
                
        //this.userLocalDirTmp = "C:/repertoire avec espaces";
        //this.userLocalDirTmp = "c:\\maarch";
        //this.userLocalDirTmp = "\\\\192.168.21.100\\Public\\montage_nas\\avec espaces";
        
        this.fM = new fileManager();
        this.fM.createUserLocalDirTmp(this.userLocalDirTmp);
        if (isWindows) {
            System.out.println("This is Windows");
            this.userLocalDirTmp = this.userLocalDirTmp + "\\maarchTmp\\";
            //this.appPath = this.userLocalDirTmp.replaceAll(" ", "%20") + "start.bat";
            //this.appPath = "\""+this.userLocalDirTmp + "start.bat\"";
            this.appPath = this.userLocalDirTmp + "start.bat";
            this.appPath_convert = this.userLocalDirTmp + "conversion.bat";
            this.os = "win";
        } else if (isMac) {
            System.out.println("This is Mac");
            this.userLocalDirTmp = this.userLocalDirTmp + "/maarchTmp/";
            this.appPath = this.userLocalDirTmp + "start.sh";
            this.appPath_convert = this.userLocalDirTmp + "conversion.sh";
            this.os = "mac";
        } else if (isUnix) {
            System.out.println("This is Unix or Linux");
            this.userLocalDirTmp = this.userLocalDirTmp + "/maarchTmp/";
            this.appPath = this.userLocalDirTmp + "start.sh";
            this.appPath_convert = this.userLocalDirTmp + "conversion.sh";
            this.os = "linux";
        } else {
            System.out.println("Your OS is not supported!!");
        }
        System.out.println("APP PATH: " + this.appPath);
        System.out.println("----------BEGIN LOCAL DIR TMP IF NOT EXISTS----------");
        
        this.fM.createUserLocalDirTmp(this.userLocalDirTmp);
        System.out.println("----------END LOCAL DIR TMP IF NOT EXISTS----------");
        
        
        System.out.println("Create the logger");
        this.logger = new myLogger(this.userLocalDirTmp);
        
        this.logger.log("Delete thefile if exists", Level.INFO);
        this.fM.deleteFilesOnDir(this.userLocalDirTmp, "thefile");
        
        if (this.psExecMode.equals("OK")) {
            this.logger.log("----------BEGIN PSEXEC MODE----------", Level.INFO);
            boolean isPsExecExists = this.fM.isPsExecFileExists(this.userLocalDirTmp + "PsExec.exe");
            if (!isPsExecExists) {
                this.logger.log("----------BEGIN TRANSFER OF PSEXEC----------", Level.INFO);
                String urlToSend = this.url + "?action=sendPsExec&objectType=" + this.objectType
                        + "&objectTable=" + this.objectTable + "&objectId=" + this.objectId;
                sendHttpRequest(urlToSend, "none",false);
                this.logger.log("MESSAGE STATUS : " + this.messageStatus.toString(), Level.INFO);
                this.logger.log("MESSAGE RESULT : ", Level.INFO);
                this.processReturn(this.messageResult);
                this.logger.log("CREATE THE FILE : " + this.userLocalDirTmp + "PsExec.exe", Level.INFO);
                this.fM.createFile(this.fileContent, this.userLocalDirTmp + "PsExec.exe");
                this.fileContent = "";
                this.logger.log("----------END TRANSFER OF PSEXEC----------", Level.INFO);
            }
            this.logger.log("----------END PSEXEC MODE----------", Level.INFO);
        }
        
        this.logger.log("----------BEGIN OPEN REQUEST----------", Level.INFO);
        String urlToSend = this.url + "?action=editObject&objectType=" + this.objectType
                        + "&objectTable=" + this.objectTable + "&objectId=" + this.objectId;
        sendHttpRequest(urlToSend, "none",false);
        this.logger.log("MESSAGE STATUS : " + this.messageStatus.toString(), Level.INFO);
        this.logger.log("MESSAGE RESULT : ", Level.INFO);
        this.processReturn(this.messageResult);
        this.logger.log("----------END OPEN REQUEST----------", Level.INFO);
        
        Integer randomNum;
        Integer minimum = 1;
        Integer maximum = 1000;
        
        randomNum = minimum + (int)(Math.random()*maximum); 
        this.fileToEdit = "thefile_" + randomNum + "." + this.fileExtension;
        
        this.logger.log("----------BEGIN CREATE THE BAT TO LAUNCH IF NECESSARY----------", Level.INFO);
        this.logger.log("create the file : "  + this.appPath, Level.INFO);
        this.fM.createBatFile(
            this.appPath, 
            this.userLocalDirTmp, 
            this.fileToEdit, 
            this.os,
            this.userMaarch,
            this.userMaarchPwd,
            this.psExecMode,
            this.userLocalDirTmp
        );
        this.logger.log("----------END CREATE THE BAT TO LAUNCH IF NECESSARY----------", Level.INFO);
        
        if ("ok".equals(this.status)) {
            this.logger.log("RESPONSE OK", Level.INFO);
            
            this.logger.log("CREATE FILE IN LOCAL PATH", Level.INFO);
            if (this.useExeConvert.equals("false")){
            	this.logger.log("---------- VBS FILE ----------", Level.INFO);
            	this.logger.log(" Path = "+this.vbsPath, Level.INFO);
            	if (this.vbsPath.equals("")) this.vbsPath = this.userLocalDirTmp + "DOC2PDF_VBS.vbs";
            	boolean isVbsExists = this.fM.isPsExecFileExists(this.vbsPath);
            	if (!isVbsExists) fM.createFile(this.fileContentVbs, this.vbsPath);
            }
            else {
            	boolean isConvExecExists = this.fM.isPsExecFileExists(this.userLocalDirTmp + "Word2Pdf.exe");
            	if (!isConvExecExists) fM.createFile(this.fileContentExe, this.userLocalDirTmp + "Word2Pdf.exe");
            }
            
            this.logger.log("----------BEGIN EXECUTION OF THE EDITOR----------", Level.INFO);
            this.logger.log("CREATE FILE IN LOCAL PATH", Level.INFO);
            this.fM.createFile(this.fileContent, this.userLocalDirTmp + this.fileToEdit);
            
            Thread theThread;
            theThread = new Thread(new ProcessLoop(this));
            
            //theThread.logger = this.logger;
            
            theThread.start();
            
            String actualContent;
            this.fileContentTosend = "";
            do {
                theThread.sleep(1000);
                actualContent = this.fM.encodeFile(this.userLocalDirTmp + this.fileToEdit);
                if (!this.fileContentTosend.equals(actualContent)) {
                    this.fileContentTosend = actualContent;
                    this.logger.log("----------[SECURITY BACKUP] BEGIN SEND OF THE OBJECT----------", Level.INFO);
                    String urlToSave = this.url + "?action=saveObject&objectType=" + this.objectType 
                                + "&objectTable=" + this.objectTable + "&objectId=" + this.objectId;
                    this.logger.log("[SECURITY BACKUP] URL TO SAVE : " + urlToSave, Level.INFO);
                    sendHttpRequest(urlToSave, this.fileContentTosend,false);
                    this.logger.log("[SECURITY BACKUP] MESSAGE STATUS : " + this.messageStatus.toString(), Level.INFO);
                }
            }
            while (theThread.isAlive());
            
            theThread.interrupt();
            
            this.logger.log("----------END EXECUTION OF THE EDITOR----------", Level.INFO);
            
            this.logger.log("----------BEGIN RETRIEVE CONTENT OF THE OBJECT----------", Level.INFO);

            this.fileContentTosend = this.fM.encodeFile(this.userLocalDirTmp + this.fileToEdit);
            
            this.logger.log("----------END RETRIEVE CONTENT OF THE OBJECT----------", Level.INFO);
            
            this.logger.log("----------CONVERSION PDF----------", Level.INFO);
            createPDF(this.userLocalDirTmp + this.fileToEdit, this.userLocalDirTmp, isUnix);
            
            String pdfFile = this.userLocalDirTmp + "thefile_" + randomNum + ".pdf";
            
            this.logger.log("----------BEGIN RETRIEVE CONTENT OF THE OBJECT----------", Level.INFO);
            if (this.fM.isPsExecFileExists(pdfFile)){           
          	  this.pdfContentTosend = fileManager.encodeFile(pdfFile);
            }
            else {
            	this.pdfContentTosend = "null";
            	this.logger.log("ERREUR DE CONVERSION PDF !", Level.INFO);
            }
            
            this.logger.log("----------END RETRIEVE CONTENT OF THE OBJECT----------", Level.INFO);
            
            this.logger.log("---------- FIN CONVERSION PDF----------", Level.INFO);
            
            String urlToSave = this.url + "?action=saveObject&objectType=" + this.objectType 
                            + "&objectTable=" + this.objectTable + "&objectId=" + this.objectId;
            this.logger.log("----------BEGIN SEND OF THE OBJECT----------", Level.INFO);
            this.logger.log("URL TO SAVE : " + urlToSave, Level.INFO);
            sendHttpRequest(urlToSave, this.fileContentTosend,true);
            this.logger.log("MESSAGE STATUS : " + this.messageStatus.toString(), Level.INFO);
            this.logger.log("LAST MESSAGE RESULT : ", Level.INFO);
            this.processReturn(this.messageResult);
            //send message to Maarch at the end
            if (!this.endMessage.isEmpty()) {
                this.sendJsMessage(this.endMessage.toString());
            }
            this.sendJsEnd();
            this.logger.log("----------END SEND OF THE OBJECT----------", Level.INFO);
        } else {
            this.logger.log("RESPONSE KO", Level.WARNING);
        }
        this.logger.log("----------END EDIT OBJECT----------", Level.INFO);
        return "ok";
    }
    
    public class ProcessLoop extends Thread {
        public DisCM disCM;
        
        public ProcessLoop(DisCM maarchCM){
            this.disCM = maarchCM;
        }

        public void run() {
            try {
            	disCM.launchProcess();
            } catch (PrivilegedActionException ex) {
                Logger.getLogger(DisCM.class.getName()).log(Level.SEVERE, null, ex);
            } catch (InterruptedException ex) {
                Logger.getLogger(DisCM.class.getName()).log(Level.SEVERE, null, ex);
            } catch (IllegalArgumentException ex) {
                Logger.getLogger(DisCM.class.getName()).log(Level.SEVERE, null, ex);
            } catch (IllegalAccessException ex) {
                Logger.getLogger(DisCM.class.getName()).log(Level.SEVERE, null, ex);
            } catch (InvocationTargetException ex) {
                Logger.getLogger(DisCM.class.getName()).log(Level.SEVERE, null, ex);
            }
        }
    }
    
    public boolean launchProcess() throws PrivilegedActionException, InterruptedException, IllegalArgumentException, IllegalAccessException, InvocationTargetException
    {
        final String exec;
        Process proc;

        this.logger.log("LAUNCH THE EDITOR !", Level.INFO);
        if ("linux".equals(this.os)) {
            exec = this.appPath;
            proc = this.fM.launchApp(exec);
        } else {
            this.logger.log("FILE TO EDIT : " + this.userLocalDirTmp + this.fileToEdit, Level.INFO);
            
            String programName;
            programName = this.fM.findGoodProgramWithExt(this.fileExtension);
            this.logger.log("PROGRAM NAME TO EDIT : " + programName, Level.INFO);
            String pathProgram;
            pathProgram = this.fM.findPathProgramInRegistry(programName);
            this.logger.log("PROGRAM PATH TO EDIT : " + pathProgram, Level.INFO);
            String options;
            options = this.fM.findGoodOptionsToEdit(this.fileExtension);
            this.logger.log("OPTION PROGRAM TO EDIT " + options, Level.INFO);
            String pathCommand;
            pathCommand = pathProgram + " " + options + "\""+this.userLocalDirTmp + this.fileToEdit+"\"";
            this.logger.log("PATH COMMAND TO EDIT " + pathCommand, Level.INFO);
            proc = this.fM.launchApp(pathCommand);
        }
        
        this.logger.log("WAIT END OF THE PROCESS", Level.INFO);
        proc.waitFor();
        this.logger.log("END OF THE PROCESS", Level.INFO);
        
        return true;
    }
    
    public void sendJsMessage(String message) throws JSException
    {
        JSObject jso;
        jso = JSObject.getWindow(this);
        this.logger.log("----------JS CALL sendAppletMsg TO MAARCH----------", Level.INFO);
        jso.call("sendAppletMsg", new String[] {String.valueOf(message)});
    }
    
    public void sendJsEnd() throws InterruptedException, JSException
    {
        JSObject jso;
        jso = JSObject.getWindow(this);
        this.logger.log("----------JS CALL endOfApplet TO MAARCH----------", Level.INFO);
        jso.call("endOfApplet", new String[] {String.valueOf(this.objectType), this.endMessage});    
    }
    
    public void sendHttpRequest(String theUrl, String postRequest, boolean endProcess) throws Exception {
        URL UrlOpenRequest = new URL(theUrl);
        HttpURLConnection HttpOpenRequest = (HttpURLConnection) UrlOpenRequest.openConnection();
        HttpOpenRequest.setDoOutput(true);
        HttpOpenRequest.setRequestMethod("POST");
        HttpOpenRequest.setRequestProperty("Cookie", this.cookie);
        if (!"none".equals(postRequest)) {
            OutputStreamWriter writer = new OutputStreamWriter(HttpOpenRequest.getOutputStream());
            if (endProcess){
            	if (!this.pdfContentTosend.equals("null"))
            		writer.write("fileContent=" + this.fileContentTosend + "&fileExtension=" + this.fileExtension+ "&pdfContent=" + this.pdfContentTosend);
            	else writer.write("fileContent=" + this.fileContentTosend + "&fileExtension=" + this.fileExtension);
            }
            else writer.write("fileContent=" + this.fileContentTosend + "&fileExtension=" + this.fileExtension);
            writer.flush();
        } else {
            OutputStreamWriter writer = new OutputStreamWriter(HttpOpenRequest.getOutputStream());
            writer.write("foo=bar");
            writer.flush();
        }
        this.parse_xml(HttpOpenRequest.getInputStream());
        HttpOpenRequest.disconnect();
    }
}

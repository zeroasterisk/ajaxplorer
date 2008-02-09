package org.argeo.ajaxplorer.jdrivers.file;

import org.apache.commons.logging.Log;
import org.apache.commons.logging.LogFactory;
import org.argeo.ajaxplorer.jdrivers.AxpAction;

public abstract class FileAction implements AxpAction {
	protected final Log log = LogFactory.getLog(getClass());
	private FileDriverContext fileDriverContext;

	public FileDriverContext getFileDriverContext() {
		return fileDriverContext;
	}

	public void setFileDriverContext(FileDriverContext fileDriverContext) {
		this.fileDriverContext = fileDriverContext;
	}

}

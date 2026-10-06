export interface FormAttributes {
	/** Identifies this form in its post, so the server reads its settings. */
	formId: string;
	/** Post type to store each submission into ('' = do not store). */
	storePostType: string;
	storeStatus: string;
	/** Name of the field used as the stored post title. */
	titleField: string;
	sendEmail: boolean;
	/** Recipients, separated by commas (empty = site admin). */
	emailTo: string;
	emailSubject: string;
	/** Body with tags: {field} and {all_fields}. */
	emailBody: string;
	[ key: string ]: unknown;
}
